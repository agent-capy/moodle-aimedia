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
}
