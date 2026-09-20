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
 * Ask an AI to describe the selected image, and put the answer in its alt text.
 *
 * Alt text is the reason this is worth having in the editor. A teacher who has
 * just dropped a diagram into a page knows what it shows and writes nothing,
 * because writing it out is dull. The model is good at exactly that, and the
 * person stays in charge: the text lands in the field and can be edited.
 *
 * @module      tiny_aimedia/describe
 * @copyright   2026 UDAGAWA Mitsuru
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {getString} from 'core/str';
import Notification from 'core/notification';

import {getContextId} from './options';

/**
 * The image the cursor is on, if it is on one.
 *
 * @param {TinyMCE} editor The editor.
 * @returns {HTMLImageElement|null} The image, or null.
 */
export const selectedImage = (editor) => {
    const node = editor.selection.getNode();

    return node && node.nodeName === 'IMG' ? node : null;
};

/**
 * Whether the button should be available at this moment.
 *
 * @param {TinyMCE} editor The editor.
 * @returns {function} The teardown function TinyMCE expects.
 */
export const followSelection = (editor) => (api) => {
    const update = () => api.setEnabled(selectedImage(editor) !== null);
    update();
    editor.on('NodeChange', update);

    return () => editor.off('NodeChange', update);
};

/**
 * Ask about the selected image and write the answer into its alt text.
 *
 * @param {TinyMCE} editor The editor.
 */
export const describeSelection = async(editor) => {
    const image = selectedImage(editor);
    if (!image) {
        Notification.addNotification({
            message: await getString('error:noimage', 'tiny_aimedia'),
            type: 'info',
        });

        return;
    }

    const original = image.getAttribute('alt');
    image.setAttribute('alt', await getString('working', 'tiny_aimedia'));

    try {
        const response = await fetchMany([{
            methodname: 'local_aimedia_describe_editor_image',
            args: {
                contextid: getContextId(editor),
                imageurl: image.getAttribute('src'),
                question: await getString('prompt', 'tiny_aimedia'),
            },
        }])[0];

        if (!response.success) {
            // Put back whatever was there. A failed request should leave the
            // page as it found it, not with a placeholder in the alt text.
            image.setAttribute('alt', original ?? '');
            Notification.addNotification({message: response.error, type: 'error'});

            return;
        }

        image.setAttribute('alt', response.text);
        editor.nodeChanged();
        Notification.addNotification({
            message: await getString('done:describe', 'tiny_aimedia'),
            type: 'success',
        });
    } catch (error) {
        image.setAttribute('alt', original ?? '');
        Notification.exception(error);
    }
};
