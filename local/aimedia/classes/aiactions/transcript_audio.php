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

namespace local_aimedia\aiactions;

use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;

/**
 * Turn a recording into text.
 *
 * Moodle's AI subsystem has four actions and every one of them takes text in.
 * Nothing in core asks an AI to listen to something, so this action is defined
 * here rather than in core_ai, and providers that can transcribe declare it the
 * same way they declare core's actions.
 *
 * Three places in core build an action's class name as 'core_ai\\aiactions\\'
 * plus its basename, so an action living anywhere else is not found there. The
 * one that bites is the enable/disable switch on the provider settings screen:
 * it writes to a key nothing reads, so this action cannot be switched off from
 * that screen. It arrives switched on and stays that way. Use a routing rule to
 * stop it. This is written up as a report for core.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transcript_audio extends base {
    /**
     * Constructor.
     *
     * The recording is passed as a stored file rather than as its contents,
     * because the router may offer the same request to more than one provider
     * and a stream read once is empty the second time.
     *
     * @param int $contextid The context the request was raised in.
     * @param int $userid The user making the request.
     * @param \stored_file $file The recording to transcribe.
     * @param string $language The language spoken, as an ISO-639-1 code, or empty to let the model decide.
     */
    public function __construct(
        int $contextid,
        /** @var int The user id requesting the action. */
        protected int $userid,
        /** @var \stored_file The recording to transcribe. */
        protected \stored_file $file,
        /** @var string The language spoken, or an empty string. */
        protected string $language = '',
    ) {
        parent::__construct($contextid);
    }

    /**
     * The class that carries this action's answer.
     *
     * Core has this method and then does not call it: manager.php rebuilds the
     * name from core_ai's own namespace instead. Overridden anyway, so that the
     * day core calls it, this action already answers correctly.
     *
     * @return string The response class name.
     */
    public static function get_response_classname(): string {
        return responses\response_transcript_audio::class;
    }

    /**
     * The name shown wherever Moodle lists actions.
     *
     * @return string The name.
     */
    public static function get_name(): string {
        return get_string('action:transcript_audio', 'local_aimedia');
    }

    /**
     * What this action is for, shown beside the name.
     *
     * @return string The description.
     */
    public static function get_description(): string {
        return get_string('action:transcript_audio:description', 'local_aimedia');
    }

    #[\Override]
    protected function get_tablename(): string {
        // Not core's ai_action_* namespace: this action is not core's to store.
        return 'local_aimedia_transcript';
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();

        $record = new \stdClass();
        $record->contenthash = $this->file->get_contenthash();
        $record->filename = $this->file->get_filename();
        $record->filesize = $this->file->get_filesize();
        $record->language = $this->language === '' ? null : $this->language;
        $record->transcript = $responsearr['transcript'] ?? null;
        $record->durationms = $responsearr['durationms'] ?? null;
        $record->userid = (int) $this->userid;
        $record->contextid = (int) $this->contextid;
        $record->timecreated = $this->timecreated;

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
