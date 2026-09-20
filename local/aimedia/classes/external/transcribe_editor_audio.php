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
use local_aimedia\aiactions\transcript_audio;
use local_aimedia\editor_file;
use local_aimedia\media_request;

/**
 * Turn a recording the user has just made in an editor into words.
 *
 * Moodle can already record straight into the editor, and what it leaves behind
 * is a player nobody can search, quote or read. This is the other half: the same
 * recording, as text, next to it.
 *
 * The recording arrives as the URL the editor is showing. Deciding whether the
 * caller may send it is the whole of editor_file, which this trusts and does
 * not repeat.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transcribe_editor_audio extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters The parameters.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Where the request is being made from, or 0 for nowhere in particular'),
            'audiourl' => new external_value(PARAM_URL, 'The src of the recording in the editor'),
        ]);
    }

    /**
     * Transcribe the recording.
     *
     * @param int $contextid Where the request is being made from.
     * @param string $audiourl The src of the recording in the editor.
     * @return array The words, or why there are not any.
     */
    public static function execute(int $contextid, string $audiourl): array {
        global $USER;

        [
            'contextid' => $contextid,
            'audiourl' => $audiourl,
        ] = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'audiourl' => $audiourl,
        ]);

        // Where the request is being made from, which the caller has to be
        // allowed to use. A teacher's permission comes from their course, and
        // routing rules and usage reports work on where a request came from,
        // so a request that says nowhere would match nothing and be reported
        // against nothing.
        $context = $contextid > 0
            ? \context::instance_by_id($contextid, MUST_EXIST)
            : \context_user::instance($USER->id);
        self::validate_context($context);
        require_capability('local/aimedia:use', $context);

        // The site's AI usage policy has to be accepted before anything is sent,
        // as the page does and as the placements Moodle ships require. Core does
        // not check it on the way through, and a button is not a place to assume
        // somebody has read anything.
        if (!\core_ai\manager::get_user_policy_status((int) $USER->id)) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error:policynotaccepted', 'local_aimedia'),
            ];
        }

        $file = editor_file::resolve($audiourl);
        if ($file === null) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error:notadraftaudio', 'local_aimedia'),
            ];
        }

        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->get_providers_for_actions([transcript_audio::class], true)[transcript_audio::class]) {
            return [
                'success' => false,
                'text' => '',
                'error' => get_string('error:noprovider', 'local_aimedia'),
            ];
        }

        $outcome = media_request::run($manager, media_request::make(
            class: transcript_audio::class,
            contextid: $context->id,
            userid: (int) $USER->id,
            file: $file,
        ));

        return [
            'success' => $outcome->success,
            'text' => (string) ($outcome->data['transcript'] ?? ''),
            'error' => $outcome->error,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure The structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether there is a transcript'),
            'text' => new external_value(PARAM_RAW, 'The words heard in the recording'),
            'error' => new external_value(PARAM_TEXT, 'Why there is no transcript'),
        ]);
    }
}
