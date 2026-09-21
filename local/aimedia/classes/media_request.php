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

namespace local_aimedia;

use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;
use core_ai\manager;
use local_aimedia\aiactions\describe_image;
use local_aimedia\aiactions\transcript_audio;

/**
 * What the page may offer, and how to turn a choice into an action.
 *
 * Kept out of the page so that it can be tested. A page script is the one place
 * in Moodle where nothing can reach the logic afterwards, and deciding which
 * actions a site can offer is exactly the kind of thing that stops being true
 * quietly.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_request {
    /**
     * @var string[] The actions this plugin defines, in the order they are offered.
     */
    public const ACTIONS = [
        transcript_audio::class,
        describe_image::class,
    ];

    /**
     * Which of them some enabled provider can actually carry out.
     *
     * Offering one that nothing can perform is an option that fails after the
     * file has been uploaded, which is the worst moment to find out.
     *
     * @param manager $manager The AI manager.
     * @return array Action class names, keyed by class, valued by display name.
     */
    public static function available(manager $manager): array {
        $available = [];
        foreach (self::ACTIONS as $class) {
            if ($manager->get_providers_for_actions([$class], true)[$class]) {
                $available[$class] = $class::get_name();
            }
        }

        return $available;
    }

    /**
     * Turn a choice on the form into the action it names.
     *
     * @param string $class The action class the form returned.
     * @param int $contextid Where the request was raised.
     * @param int $userid Who raised it.
     * @param \stored_file $file What they uploaded.
     * @param string $question What they asked, for the actions that take one.
     * @return base The action.
     * @throws \coding_exception When the class is not one this plugin offers.
     */
    public static function make(
        string $class,
        int $contextid,
        int $userid,
        \stored_file $file,
        string $question = '',
    ): base {
        if (!in_array($class, self::ACTIONS, true)) {
            throw new \coding_exception('Not an action this plugin offers: ' . $class);
        }

        if ($class === describe_image::class) {
            return new describe_image(
                contextid: $contextid,
                userid: $userid,
                file: $file,
                prompttext: $question,
            );
        }

        // A recording is transcribed, so whatever was typed has nowhere to go.
        return new transcript_audio(contextid: $contextid, userid: $userid, file: $file);
    }

    /**
     * Run an action and report what came back, including a refusal.
     *
     * A provider is expected to return a failed response rather than throw, and every
     * placement Moodle ships takes that on trust. One kind of failure cannot be said
     * that way: core tries each provider in turn until one succeeds, so a provider
     * that turned a request down as a matter of policy has no way of saying that the
     * answer is no rather than "not me". The AI Router says it by throwing, which is
     * the only thing that stops core's loop.
     *
     * Being told no is not an error on this plugin's part, and it should not look like
     * one, so the refusal is turned back into the same shape as any other failure and
     * shown as a message. The class named here belongs to another plugin and is simply
     * never matched on a site without it; nothing else is caught, so a provider that
     * throws because something is genuinely wrong still surfaces as it should.
     *
     * The file the action carries is checked against this plugin's limits first. A
     * provider reads the whole of it into memory, and an image is base64 encoded into
     * a JSON body on the way, so the size is not somebody else's problem.
     *
     * @param manager $manager The AI manager.
     * @param base $action The action to run.
     * @return \stdClass success (bool), data (array) and error (string).
     */
    public static function run(manager $manager, base $action): \stdClass {
        // The last point both roads pass through before anything is sent. A form can
        // state a limit; the editor buttons never see a form, so the limit is checked
        // here instead of being trusted to whichever screen happened to be used.
        $file = $action->get_configuration('file');
        if ($file instanceof \stored_file) {
            $refused = media_limits::check($action::class, $file);
            if ($refused !== null) {
                return (object) ['success' => false, 'data' => [], 'error' => $refused];
            }
        }

        try {
            $response = $manager->process_action($action);
        } catch (\local_airouter\exception\declined_request $e) {
            return (object) ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }

        if (!$response->get_success()) {
            return (object) [
                'success' => false,
                'data' => [],
                'error' => self::failure_message($response),
            ];
        }

        return (object) ['success' => true, 'data' => $response->get_response_data(), 'error' => ''];
    }

    /**
     * What to put on the screen when the request failed.
     *
     * Moodle 5.1 made the message optional on a failed response and moved the part it
     * insists on into a short error name, which names the fault for a log rather than
     * saying anything to the person who asked. So a failure can arrive with nothing
     * worth showing, and an empty error box is worse than a plain sentence.
     *
     * @param response_base $response The failed response.
     * @return string Something the person can read.
     */
    protected static function failure_message(response_base $response): string {
        $message = trim((string) $response->get_errormessage());

        return $message !== '' ? $message : get_string('error:requestfailed', 'local_aimedia');
    }
}
