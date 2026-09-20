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

namespace local_aimedia\form;

use local_aimedia\aiactions\transcript_audio;

/**
 * Ask an AI about a file.
 *
 * One form for both actions, because the difference between them is one field:
 * a picture is asked a question, a recording is not.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class media_form extends \moodleform {
    /**
     * @var int The largest file this form accepts, in bytes.
     *
     * The picker is shown before the action is chosen, so it can only enforce the
     * most generous of the limits. media_limits checks the narrower one once the
     * action is known, which is also where the editor buttons are checked.
     */
    public const MAX_BYTES = 30 * 1024 * 1024;

    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $available = $this->_customdata['actions'] ?? [];

        $mform->addElement('hidden', 'contextid', $this->_customdata['contextid'] ?? 0);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement(
            'select',
            'action',
            get_string('form:action', 'local_aimedia'),
            $available,
        );
        $mform->setType('action', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'filepicker',
            'media',
            get_string('form:file', 'local_aimedia'),
            null,
            [
                'maxbytes' => \local_aimedia\media_limits::form_max_bytes(),
                'accepted_types' => \local_aimedia\media_limits::accepted_types(),
            ],
        );
        $mform->addRule('media', null, 'required', null, 'client');
        $mform->addHelpButton('media', 'form:file', 'local_aimedia');

        $mform->addElement(
            'textarea',
            'question',
            get_string('form:question', 'local_aimedia'),
            ['rows' => 3, 'cols' => 60],
        );
        $mform->setType('question', PARAM_TEXT);
        $mform->addHelpButton('question', 'form:question', 'local_aimedia');
        // A recording is transcribed, not interrogated: there is nothing to ask.
        $mform->hideIf('question', 'action', 'eq', transcript_audio::class);

        $this->add_action_buttons(false, get_string('form:submit', 'local_aimedia'));
    }
}
