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
 * Ask an AI about a recording or a picture.
 *
 * A page rather than a placement, because the placements Moodle ships put AI into
 * text the user is already editing, and there is nowhere in Moodle that a person
 * hands over a file and expects words back. This is the smallest thing that lets
 * somebody do that, and it exercises the whole chain: the AI subsystem, whichever
 * provider the router picks, and the provider's own processing.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

// moodleform is not autoloaded, so a form class extending it needs this first.
require_once($CFG->libdir . '/formslib.php');

use local_aimedia\form\media_form;
use local_aimedia\media_request;

require_login();

$context = context_system::instance();
$url = new moodle_url('/local/aimedia/index.php');

require_capability('local/aimedia:use', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('pluginname', 'local_aimedia'));
$PAGE->set_heading(get_string('pluginname', 'local_aimedia'));
$PAGE->set_pagelayout('standard');

$manager = \core\di::get(\core_ai\manager::class);

$actions = media_request::available($manager);

// The site's AI policy is shown before anything is sent, the way the placements
// that ship with Moodle show it.
$accepted = \core_ai\manager::get_user_policy_status($USER->id);
if (!$accepted && optional_param('acceptpolicy', 0, PARAM_BOOL) && confirm_sesskey()) {
    \core_ai\manager::user_policy_accepted($USER->id, $context->id);
    redirect($url);
}

$form = new media_form($url->out(false), ['actions' => $actions]);
$result = null;
$failure = null;

if ($actions && $accepted && ($data = $form->get_data())) {
    $draftid = (int) $data->media;
    $storage = get_file_storage();
    file_save_draft_area_files($draftid, $context->id, 'local_aimedia', 'submitted', $draftid);
    $files = $storage->get_area_files($context->id, 'local_aimedia', 'submitted', $draftid, 'id', false);
    $file = reset($files);

    if ($file === false) {
        $failure = get_string('error:nofile', 'local_aimedia');
    } else {
        // Only what is on offer, and only by exact match: the select's value
        // reaches here as text, and falling back to a default would quietly do
        // something other than what was asked.
        $class = (string) $data->action;
        if (!array_key_exists($class, $actions)) {
            throw new moodle_exception('error:unknownaction', 'local_aimedia');
        }

        $action = media_request::make(
            class: $class,
            contextid: $context->id,
            userid: $USER->id,
            file: $file,
            question: trim((string) ($data->question ?? '')),
        );

        $response = $manager->process_action($action);
        if ($response->get_success()) {
            $payload = $response->get_response_data();
            $result = (object) [
                'text' => (string) ($payload['transcript'] ?? $payload['generatedcontent'] ?? ''),
                'model' => (string) ($payload['model'] ?? ''),
                'filename' => $file->get_filename(),
            ];
        } else {
            $failure = $response->get_errormessage();
        }

        // The upload was the means, not the point. Keeping somebody's voice or face
        // on the server afterwards would be keeping it for no reason.
        $file->delete();
    }
}

echo $OUTPUT->header();

if (!$accepted) {
    echo $OUTPUT->heading(get_string('aiusagepolicy', 'core_ai'), 3);
    echo $OUTPUT->box(format_text(get_string('userpolicy', 'core_ai'), FORMAT_HTML));
    echo $OUTPUT->single_button(
        new moodle_url($url, ['acceptpolicy' => 1, 'sesskey' => sesskey()]),
        get_string('acceptai', 'core_ai'),
    );
} else if (!$actions) {
    echo $OUTPUT->notification(get_string('error:noprovider', 'local_aimedia'), 'warning');
} else {
    echo html_writer::div(get_string('intro', 'local_aimedia'), 'text-muted mb-3');
    $form->display();
}

if ($failure !== null) {
    echo $OUTPUT->notification($failure, 'error');
}

if ($result !== null) {
    echo $OUTPUT->heading(get_string('result', 'local_aimedia'), 3);
    echo html_writer::div(
        get_string('result:about', 'local_aimedia', (object) [
            'filename' => s($result->filename),
            'model' => s($result->model),
        ]),
        'text-muted small mb-2',
    );
    echo $OUTPUT->box(format_text($result->text, FORMAT_PLAIN));
}

echo $OUTPUT->footer();
