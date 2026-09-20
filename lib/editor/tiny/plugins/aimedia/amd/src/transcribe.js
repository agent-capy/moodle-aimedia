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
 * Ask an AI to transcribe the selected recording, and put the words beside it.
 *
 * Moodle can already record straight into the editor, and what it leaves is a
 * player: nothing to search, nothing to quote, and nothing at all for somebody
 * who cannot hear it. The words belong next to the recording, in the post, where
 * the author can read them over and correct them before anybody else sees them.
 *
 * @module      tiny_aimedia/transcribe
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
 * @type {WeakSet<TinyMCE>}
 */
const busy = new WeakSet();

/**
 * The recording the cursor is on, if it is on one.
 *
 * @param {TinyMCE} editor The editor.
 * @returns {HTMLAudioElement|null} The player, or null.
 */
export const selectedAudio = (editor) => {
    const node = editor.selection.getNode();

    return node && node.nodeName === 'AUDIO' ? node : null;
};

/**
 * Where the recording is, whichever way it was put in the page.
 *
 * What Moodle's own recorder inserts carries the address on a child source
 * element; what somebody pastes may carry it on the player itself.
 *
 * @param {HTMLAudioElement} audio The player.
 * @returns {string|null} The address, or null when there is not one.
 */
export const audioSource = (audio) => audio.getAttribute('src')
    || audio.querySelector('source')?.getAttribute('src')
    || null;

/**
 * Whether the button should be available at this moment.
 *
 * @param {TinyMCE} editor The editor.
 * @returns {function} The teardown function TinyMCE expects.
 */
export const followSelection = (editor) => (api) => {
    const update = () => {
        const audio = selectedAudio(editor);
        api.setEnabled(audio !== null && audioSource(audio) !== null);
    };
    update();
    editor.on('NodeChange', update);

    return () => editor.off('NodeChange', update);
};

/**
 * The block the recording sits in, which is what the words go after.
 *
 * A paragraph cannot hold another paragraph, so putting the words directly
 * after the player would leave the editor to repair the markup, and it repairs
 * it by moving things.
 *
 * @param {TinyMCE} editor The editor.
 * @param {HTMLAudioElement} audio The player.
 * @returns {HTMLElement} The element to insert after.
 */
const blockOf = (editor, audio) => editor.dom.getParent(audio, editor.dom.isBlock) || audio;

/**
 * Transcribe the selected recording and put the words after it.
 *
 * @param {TinyMCE} editor The editor.
 */
export const transcribeSelection = async(editor) => {
    const audio = selectedAudio(editor);
    const source = audio ? audioSource(audio) : null;
    if (!source) {
        Notification.addNotification({
            message: await getString('error:noaudio', 'tiny_aimedia'),
            type: 'info',
        });

        return;
    }

    if (busy.has(editor)) {
        // Asked again before the first answer arrived.
        return;
    }

    // Nothing goes into the page until there is something to put there. A
    // placeholder paragraph is part of the content: it is in the page the moment
    // somebody saves, whether or not the answer ever arrives. TinyMCE's own busy
    // overlay says the same thing and is not content.
    busy.add(editor);
    editor.setProgressState(true);

    try {
        const response = await fetchMany([{
            methodname: 'local_aimedia_transcribe_editor_audio',
            args: {contextid: getContextId(editor), audiourl: source},
        }])[0];

        if (!response.success) {
            Notification.addNotification({message: response.error, type: 'error'});

            return;
        }
        if (editor.removed || !audio.isConnected) {
            // The editor or the recording went away while the model was
            // listening, so there is nowhere for the words to go.
            return;
        }

        // Inside a transaction, so that one press of the button is one press of
        // "undo" afterwards, and the editor knows it has been changed.
        editor.undoManager.transact(() => {
            const paragraph = editor.dom.create('p');
            // Assigned as text, never as markup: this is what a model heard, and
            // the editor is not the place to find out that it heard a tag.
            paragraph.textContent = response.text;
            editor.dom.insertAfter(paragraph, blockOf(editor, audio));
        });
        editor.nodeChanged();
        Notification.addNotification({
            message: await getString('done:transcribe', 'tiny_aimedia'),
            type: 'success',
        });
    } catch (error) {
        Notification.exception(error);
    } finally {
        busy.delete(editor);
        editor.setProgressState(false);
    }
};
