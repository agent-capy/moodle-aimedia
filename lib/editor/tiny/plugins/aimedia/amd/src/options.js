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
 * Where this editor is, as the buttons need to report it.
 *
 * Both buttons raise an AI action, and an action carries the context it was
 * raised in. Routing rules and usage reports work on that context, so an
 * action raised from nowhere in particular matches no course rule and is
 * reported against no course.
 *
 * @module      tiny_aimedia/options
 * @copyright   2026 UDAGAWA Mitsuru
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getPluginOptionName} from 'editor_tiny/options';

import {pluginName} from './common';

const contextIdName = getPluginOptionName(pluginName, 'contextid');

/**
 * Declare the options this plugin is given.
 *
 * @param {TinyMCE} editor The editor.
 */
export const register = (editor) => {
    editor.options.register(contextIdName, {
        processor: 'number',
        "default": 0,
    });
};

/**
 * The context this editor is in.
 *
 * @param {TinyMCE} editor The editor.
 * @returns {number} The context id, or 0 where there is not one.
 */
export const getContextId = (editor) => editor.options.get(contextIdName);
