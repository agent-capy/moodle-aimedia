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
 * Ask about a picture.
 *
 * Moodle's generate_image writes text and returns a picture. Nothing in core goes
 * the other way, so a vision model has no action to be reached through. This is
 * that action: a picture and a question about it, answered in text.
 *
 * ⚠ Core builds an action's class name from its own namespace in three places, so
 * an action defined here is not found there. The one that shows is the enable and
 * disable switch on the provider settings screen, which writes a key nothing
 * reads. Stop this action with a routing rule instead.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class describe_image extends base {
    /**
     * Constructor.
     *
     * @param int $contextid The context the request was raised in.
     * @param int $userid The user making the request.
     * @param \stored_file $file The picture to ask about.
     * @param string $prompttext What to ask about it.
     */
    public function __construct(
        int $contextid,
        /** @var int The user id requesting the action. */
        protected int $userid,
        /** @var \stored_file The picture to ask about. */
        protected \stored_file $file,
        /** @var string What to ask about the picture. */
        protected string $prompttext = '',
    ) {
        parent::__construct($contextid);
    }

    /**
     * The class that carries this action's answer.
     *
     * @return string The response class name.
     */
    public static function get_response_classname(): string {
        return responses\response_describe_image::class;
    }

    /**
     * The name shown wherever Moodle lists actions.
     *
     * @return string The name.
     */
    public static function get_name(): string {
        return get_string('action:describe_image', 'local_aimedia');
    }

    /**
     * What this action is for, shown beside the name.
     *
     * @return string The description.
     */
    public static function get_description(): string {
        return get_string('action:describe_image:description', 'local_aimedia');
    }

    /**
     * What the model is told before the user's question.
     *
     * Core reads this from its own language strings, which have nothing for an
     * action it does not define, so it is supplied here.
     *
     * @return string The instruction.
     */
    public static function get_system_instruction(): string {
        return get_string('action:describe_image:instruction', 'local_aimedia');
    }

    /**
     * What to ask when the caller asked nothing in particular.
     *
     * @return string The question.
     */
    public function get_question(): string {
        return $this->prompttext !== ''
            ? $this->prompttext
            : get_string('action:describe_image:defaultprompt', 'local_aimedia');
    }

    #[\Override]
    protected function get_tablename(): string {
        return 'local_aimedia_describe';
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();

        $record = new \stdClass();
        $record->prompt = $this->get_question();
        $record->contenthash = $this->file->get_contenthash();
        $record->filename = $this->file->get_filename();
        $record->filesize = $this->file->get_filesize();
        $record->generatedcontent = $responsearr['generatedcontent'] ?? null;
        $record->finishreason = $responsearr['finishreason'] ?? null;
        $record->prompttokens = $responsearr['prompttokens'] ?? null;
        $record->completiontokens = $responsearr['completiontokens'] ?? null;
        $record->userid = (int) $this->userid;
        $record->contextid = (int) $this->contextid;
        $record->timecreated = $this->timecreated;

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
