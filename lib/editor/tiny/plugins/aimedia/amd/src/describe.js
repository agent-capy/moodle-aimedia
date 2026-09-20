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
 * Editors with a request of this kind already out.
 *
 * Per editor rather than per image: the overlay stops the second press anyway,
 * and an editor can only be waiting for one of these at a time.
 *
 * @type {WeakSet<TinyMCE>}
 */
const busy = new WeakSet();

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
    if (busy.has(editor)) {
        // Asked again before the first answer arrived. Two answers for one image
        // is one answer too many, and the second would overwrite the first.
        return;
    }

    // Nothing is written into the page while the request is out. The alt text is
    // part of the content: a placeholder put there is in the page the moment
    // somebody saves, and it was also what a second attempt saw as the original
    // text to put back. TinyMCE's own busy overlay says the same thing and is
    // not content.
    busy.add(editor);
    editor.setProgressState(true);

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
            Notification.addNotification({message: response.error, type: 'error'});

            return;
        }
        if (editor.removed || !image.isConnected) {
            // The editor or the image went away while the model was thinking.
            // An answer to a question about something that is no longer there
            // has nowhere to go.
            return;
        }

        // Inside a transaction, so that one press of the button is one press of
        // "undo" afterwards. It also marks the editor as changed, which is what
        // every "you have unsaved work" prompt in Moodle reads.
        editor.undoManager.transact(() => {
            image.setAttribute('alt', response.text);
        });
        editor.nodeChanged();
        Notification.addNotification({
            message: await getString('done:describe', 'tiny_aimedia'),
            type: 'success',
        });
    } catch (error) {
        Notification.exception(error);
    } finally {
        busy.delete(editor);
        editor.setProgressState(false);
    }
};
