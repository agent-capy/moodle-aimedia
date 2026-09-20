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
 * Library functions for local_aimedia.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Put the page somewhere it can be found.
 *
 * A page nobody can reach without knowing its address is a page nobody uses, so
 * it goes in the site navigation for the people allowed to use it site-wide.
 * Somebody whose permission comes from a course will not see it here, and does
 * not need to: the course has its own entry below.
 *
 * @param global_navigation $navigation The navigation tree.
 */
function local_aimedia_extend_navigation(global_navigation $navigation): void {
    if (!has_capability('local/aimedia:use', context_system::instance())) {
        return;
    }

    $navigation->add(
        get_string('pluginname', 'local_aimedia'),
        new moodle_url('/local/aimedia/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_aimedia',
        new pix_icon('i/ai', ''),
    );
}

/**
 * Offer the page inside a course, for the people teaching it.
 *
 * A teacher writing course content is in the course, and their permission comes
 * from the course. The link carries the course context so that the request is
 * made there: routing rules and usage reports work on where a request came
 * from, and a request made from nowhere in particular matches nothing.
 *
 * @param navigation_node $navigation The course navigation node.
 * @param stdClass $course The course.
 * @param context_course $context The course context.
 */
function local_aimedia_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context,
): void {
    if (!has_capability('local/aimedia:use', $context)) {
        return;
    }

    $navigation->add(
        get_string('pluginname', 'local_aimedia'),
        new moodle_url('/local/aimedia/index.php', ['contextid' => $context->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_aimedia',
        new pix_icon('i/ai', ''),
    );
}
