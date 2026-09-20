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

/**
 * The file behind a URL an editor is showing, when the caller may send it.
 *
 * This is the security boundary for everything the editor buttons do. A picture
 * or a recording arrives as the URL the editor is displaying, which is a draft
 * file belonging to whoever is editing. A draft area lives in its owner's user
 * context and the URL carries that context id, so without looking at it anybody
 * could name somebody else's drafts and have an AI read them out.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor_file {
    /**
     * The caller's own draft file that a URL points at, if it is one.
     *
     * Only the caller's drafts, and only drafts: a saved file would need whatever
     * permission its own component decides, which is not this function's to judge.
     *
     * @param string $url The src the editor is showing.
     * @return \stored_file|null The file, or null when the URL is not one of these.
     */
    public static function resolve(string $url): ?\stored_file {
        global $CFG, $USER;

        $prefix = $CFG->wwwroot . '/draftfile.php/';
        if (!str_starts_with($url, $prefix)) {
            return null;
        }

        $parts = explode('/', urldecode(substr($url, strlen($prefix))));
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
