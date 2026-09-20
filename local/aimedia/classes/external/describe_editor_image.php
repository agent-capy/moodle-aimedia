<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aimedia\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_aimedia\media_request;
use local_aimedia\aiactions\describe_image;

/**
 * Ask about a picture the user has just put in an editor.
 *
 * ⚠ The picture arrives as the URL the editor is showing, which is a draft file
 * belonging to whoever is editing. That makes the check that matters here: a
 * draft area lives in its owner's user context, so the context in the URL has to
 * be the caller's own. Without that, one person could ask about another's drafts.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class describe_editor_image extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'imageurl' => new external_value(PARAM_URL, 'The src of the image in the editor'),
            'question' => new external_value(PARAM_TEXT, 'What to ask about it', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Ask about the picture.
     *
     * @param string $imageurl The src of the image in the editor.
     * @param string $question What to ask about it.
     * @return array The answer, or why there is not one.
     */
    public static function execute(string $imageurl, string $question = ''): array {
        global $USER;

        [
            'imageurl' => $imageurl,
            'question' => $question,
        ] = self::validate_parameters(self::execute_parameters(), [
            'imageurl' => $imageurl,
            'question' => $question,
        ]);

        $context = \context_user::instance($USER->id);
        self::validate_context($context);
        require_capability('local/aimedia:use', \context_system::instance());

        $file = self::draft_file($imageurl);
        if ($file === null) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error:notadraft', 'local_aimedia'),
            ];
        }

        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->get_providers_for_actions([describe_image::class], true)[describe_image::class]) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error:noprovider', 'local_aimedia'),
            ];
        }

        $response = $manager->process_action(media_request::make(
            class: describe_image::class,
            contextid: $context->id,
            userid: (int) $USER->id,
            file: $file,
            question: trim($question),
        ));

        return [
            'success' => $response->get_success(),
            'text' => $response->get_success()
                ? (string) ($response->get_response_data()['generatedcontent'] ?? '')
                : '',
            'error' => $response->get_success() ? '' : $response->get_errormessage(),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether there is an answer'),
            'text' => new external_value(PARAM_RAW, 'The answer'),
            'error' => new external_value(PARAM_TEXT, 'Why there is no answer'),
        ]);
    }

    /**
     * The caller's own draft file that a URL points at, if it is one.
     *
     * Only the caller's drafts, and only drafts: a saved file would need whatever
     * permission its own component decides, which is not this function's to judge.
     *
     * @param string $imageurl The src of the image in the editor.
     * @return \stored_file|null The file, or null when the URL is not one of these.
     */
    protected static function draft_file(string $imageurl): ?\stored_file {
        global $CFG, $USER;

        $prefix = $CFG->wwwroot . '/draftfile.php/';
        if (!str_starts_with($imageurl, $prefix)) {
            return null;
        }

        $parts = explode('/', urldecode(substr($imageurl, strlen($prefix))));
        // The shape is contextid / user / draft / itemid / [path...] / filename.
        if (count($parts) < 5 || $parts[1] !== 'user' || $parts[2] !== 'draft') {
            return null;
        }

        $contextid = (int) $parts[0];
        if ($contextid !== \context_user::instance($USER->id)->id) {
            // Somebody else's drafts, or a context that is not a user's at all.
            return null;
        }

        $itemid = (int) $parts[3];
        $filename = array_pop($parts);
        $path = array_slice($parts, 4);
        $filepath = '/' . (implode('/', $path) === '' ? '' : implode('/', $path) . '/');

        $file = get_file_storage()->get_file($contextid, 'user', 'draft', $itemid, $filepath, $filename);

        return $file === false || $file->is_directory() ? null : $file;
    }
}
