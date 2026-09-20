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
 * Tiny AI media commands.
 *
 * @module      tiny_aimedia/commands
 * @copyright   2026 UDAGAWA Mitsuru
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getButtonImage} from 'editor_tiny/utils';
import {get_string as getString} from 'core/str';

import {component, describeButtonName, describeIcon, transcribeButtonName, transcribeIcon} from './common';
import {describeSelection, followSelection as followImage} from './describe';
import {transcribeSelection, followSelection as followAudio} from './transcribe';

export const getSetup = async() => {
    const [describeText, describeImage, transcribeText, transcribeImage] = await Promise.all([
        getString('describe', component),
        getButtonImage('icon', component),
        getString('transcribe', component),
        getButtonImage('transcribe', component),
    ]);

    return (editor) => {
        editor.ui.registry.addIcon(describeIcon, describeImage.html);
        editor.ui.registry.addIcon(transcribeIcon, transcribeImage.html);

        // Each one is offered twice, as a button and as a menu item, because a
        // toolbar can be configured down to nothing and the menu is where a
        // person looks when the button is not there.
        const commands = [
            {
                name: describeButtonName,
                icon: describeIcon,
                text: describeText,
                onAction: () => describeSelection(editor),
                onSetup: followImage(editor),
            },
            {
                name: transcribeButtonName,
                icon: transcribeIcon,
                text: transcribeText,
                onAction: () => transcribeSelection(editor),
                onSetup: followAudio(editor),
            },
        ];

        commands.forEach(({name, icon, text, onAction, onSetup}) => {
            editor.ui.registry.addButton(name, {icon, tooltip: text, onAction, onSetup});
            editor.ui.registry.addMenuItem(name, {icon, text, onAction, onSetup});
        });
    };
};
