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
 * Capabilities for local_aimedia.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Sending a recording or a picture to an AI costs the site money and sends
    // somebody's voice or face outside it, so who has it is worth deciding.
    //
    // Declared at course level so that it can be given to a role that is assigned
    // in a course, which is where teachers are. Declared at site level it could
    // only be given to a role assigned site-wide, which means everybody.
    //
    // Allowed for the people who write course content, which is what the AI
    // placements Moodle ships do. It stops short of students: a picture or a
    // recording is somebody's face or voice and each request costs the site
    // money, so letting a whole cohort send them is a decision for the site
    // rather than a default. An administrator can add students in one tick.
    'local/aimedia:use' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
