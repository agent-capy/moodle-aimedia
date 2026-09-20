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

use local_aimedia\aiactions\describe_image;
use local_aimedia\aiactions\transcript_audio;

/**
 * What this plugin is willing to send, and how big.
 *
 * The upload form states a limit and a list of accepted types, and a form is a
 * courtesy rather than a control: the editor buttons reach the same actions through
 * web services, where nothing has been through a form at all. The limits therefore
 * live here, where both roads pass, rather than in the form that only one of them
 * uses.
 *
 * Size matters more than it looks. A provider reads the whole file into memory to
 * send it, and an image is base64 encoded into a JSON body on the way, which costs
 * several times the file again. The limit for a picture is accordingly lower than
 * the limit for a recording, which is sent as multipart and copied once.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_limits {
    /** @var int The largest recording this plugin will send, in bytes. */
    public const AUDIO_BYTES = 30 * 1024 * 1024;

    /** @var int The largest picture this plugin will send, in bytes. */
    public const IMAGE_BYTES = 8 * 1024 * 1024;

    /**
     * @var array[] What each action accepts, keyed by action class.
     *
     * The type groups are core's own, so that what the form offers and what the
     * editor buttons accept are described by the same list rather than by two lists
     * that agree today.
     */
    protected const LIMITS = [
        transcript_audio::class => ['bytes' => self::AUDIO_BYTES, 'groups' => ['audio']],
        describe_image::class => ['bytes' => self::IMAGE_BYTES, 'groups' => ['web_image']],
    ];

    /**
     * The largest file this action will send.
     *
     * @param string $class The action class.
     * @return int Bytes. The smallest limit for an action nothing is known about.
     */
    public static function max_bytes(string $class): int {
        return self::LIMITS[$class]['bytes'] ?? self::IMAGE_BYTES;
    }

    /**
     * The media types this action accepts, as core's own type groups.
     *
     * @param string $class The action class.
     * @return string[] The group names.
     */
    public static function groups(string $class): array {
        return self::LIMITS[$class]['groups'] ?? [];
    }

    /**
     * Everything any of the actions accepts, for the one file picker that serves them all.
     *
     * @return string[] The group names.
     */
    public static function accepted_types(): array {
        $groups = [];
        foreach (self::LIMITS as $limit) {
            $groups = array_merge($groups, $limit['groups']);
        }

        return array_values(array_unique($groups));
    }

    /**
     * The limit the file picker is given, which is the most generous of the actions.
     *
     * The picker is shown before the action is chosen, so it cannot enforce the
     * narrower limit. check() does that afterwards, for whichever action was picked.
     *
     * @return int Bytes.
     */
    public static function form_max_bytes(): int {
        return max(array_column(self::LIMITS, 'bytes'));
    }

    /**
     * Whether this file may be sent for this action.
     *
     * @param string $class The action class.
     * @param \stored_file $file The file somebody wants sent.
     * @return string|null A message for the person, or null when the file is fine.
     */
    public static function check(string $class, \stored_file $file): ?string {
        $limit = self::max_bytes($class);
        if ($file->get_filesize() > $limit) {
            return get_string('error:filetoolarge', 'local_aimedia', display_size($limit));
        }

        $groups = self::groups($class);
        if (!$groups) {
            return null;
        }
        // Core's own answer to "is this one of those", so that the list cannot drift
        // from the one the file picker was given.
        $accepted = file_get_typegroup('type', $groups);
        if (!in_array($file->get_mimetype(), $accepted, true)) {
            return get_string('error:filetypenotaccepted', 'local_aimedia');
        }

        return null;
    }
}
