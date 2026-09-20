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

/**
 * Web services for local_aimedia.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aimedia_describe_editor_image' => [
        'classname' => 'local_aimedia\external\describe_editor_image',
        'description' => 'Ask an AI about a picture in the editor.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/aimedia:use',
    ],
    'local_aimedia_transcribe_editor_audio' => [
        'classname' => 'local_aimedia\external\transcribe_editor_audio',
        'description' => 'Turn a recording in the editor into text.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/aimedia:use',
    ],
];
