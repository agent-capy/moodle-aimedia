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

namespace local_aimedia\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests that a person can see and remove what this plugin kept about them.
 *
 * These tables held no user id at first, on the reasoning that they named nobody.
 * They were reachable from a person all along through core's action register, so
 * the reasoning was wrong and no privacy request could reach them. That is what
 * these tests are here to stop happening again.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Record one transcription and one picture question for somebody.
     *
     * @param int $userid Whose requests these are.
     * @param int $contextid Where they were made.
     */
    protected function record(int $userid, int $contextid): void {
        global $DB;

        $DB->insert_record('local_aimedia_transcript', (object) [
            'contenthash' => str_repeat('a', 40),
            'filename' => 'recording-audio.ogg',
            'filesize' => 1024,
            'transcript' => 'what this person said out loud',
            'userid' => $userid,
            'contextid' => $contextid,
            'timecreated' => time(),
        ]);
        $DB->insert_record('local_aimedia_describe', (object) [
            'prompt' => 'what is in this picture?',
            'contenthash' => str_repeat('b', 40),
            'filename' => 'photo.jpg',
            'filesize' => 2048,
            'generatedcontent' => 'a description of their photograph',
            'userid' => $userid,
            'contextid' => $contextid,
            'timecreated' => time(),
        ]);
    }

    public function test_the_contexts_somebody_used_it_in_are_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->record((int) $user->id, (int) $context->id);

        $found = provider::get_contexts_for_userid((int) $user->id)->get_contextids();

        $this->assertSame([(int) $context->id], array_map('intval', array_values($found)));
        $this->assertSame([], provider::get_contexts_for_userid((int) $other->id)->get_contextids());
    }

    public function test_the_people_who_used_it_in_a_context_are_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->record((int) $user->id, (int) $context->id);

        $userlist = new userlist($context, 'local_aimedia');
        provider::get_users_in_context($userlist);

        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));
    }

    public function test_what_was_kept_is_exported(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->record((int) $user->id, (int) $context->id);

        provider::export_user_data(new approved_contextlist($user, 'local_aimedia', [$context->id]));

        $this->assertTrue(writer::with_context($context)->has_any_data());
    }

    public function test_a_request_to_be_forgotten_removes_it(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->record((int) $user->id, (int) $context->id);
        $this->record((int) $other->id, (int) $context->id);

        provider::delete_data_for_user(new approved_contextlist($user, 'local_aimedia', [$context->id]));

        // Theirs is gone and nobody else's went with it.
        $this->assertSame(0, $DB->count_records('local_aimedia_transcript', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_aimedia_describe', ['userid' => $user->id]));
        $this->assertSame(1, $DB->count_records('local_aimedia_transcript', ['userid' => $other->id]));
    }

    public function test_clearing_a_context_removes_everybodys(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $elsewhere = \context_system::instance();
        $this->record((int) $user->id, (int) $context->id);
        $this->record((int) $other->id, (int) $context->id);
        $this->record((int) $user->id, (int) $elsewhere->id);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('local_aimedia_transcript', ['contextid' => $context->id]));
        // The same person's request made somewhere else is not this context's to remove.
        $this->assertSame(1, $DB->count_records('local_aimedia_transcript', ['contextid' => $elsewhere->id]));
    }
}
