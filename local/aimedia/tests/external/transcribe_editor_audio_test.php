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

namespace local_aimedia\external;

/**
 * Tests for transcribing a recording made in the editor.
 *
 * What is tested here is everything that happens before a provider is asked:
 * who may ask, whose recording it is, and what the caller is told when there is
 * nothing on the site that can listen to it.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(transcribe_editor_audio::class)]
final class transcribe_editor_audio_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Put a recording in somebody's draft area and return the URL the editor shows.
     *
     * @param int $userid Whose draft area.
     * @return string The URL.
     */
    protected function recording(int $userid): string {
        global $CFG;

        $contextid = \context_user::instance($userid)->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'recording-audio.ogg',
        ], 'pretend this is a recording');

        return $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/7/recording-audio.ogg';
    }

    public function test_somebody_elses_recording_is_refused(): void {
        $owner = $this->getDataGenerator()->create_user();
        $url = $this->recording((int) $owner->id);

        $this->setAdminUser();
        $result = transcribe_editor_audio::execute(0, $url);

        $this->assertFalse($result['success']);
        $this->assertSame('', $result['text']);
        $this->assertSame(get_string('error:notadraftaudio', 'local_aimedia'), $result['error']);
    }

    public function test_a_site_that_cannot_listen_says_so_rather_than_failing(): void {
        // No AI provider is installed in a test site, so this is the honest answer
        // and it has to arrive as an answer, not as an exception in the editor.
        $this->setAdminUser();
        $result = transcribe_editor_audio::execute(0, $this->recording((int) get_admin()->id));

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:noprovider', 'local_aimedia'), $result['error']);
    }

    public function test_the_capability_is_what_allows_it(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $url = $this->recording((int) $user->id);

        $this->expectException(\required_capability_exception::class);
        transcribe_editor_audio::execute(0, $url);
    }

    public function test_a_teacher_may_ask_from_their_own_course(): void {
        // The case this is for: somebody writing course content, whose permission
        // comes from the course they are teaching and from nowhere else.
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/aimedia:use', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $teacher->id, $context->id);

        $this->setUser($teacher);
        $result = transcribe_editor_audio::execute($context->id, $this->recording((int) $teacher->id));

        // Allowed through: the answer is about the site, not about permission.
        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:noprovider', 'local_aimedia'), $result['error']);
    }

    public function test_a_course_the_caller_has_no_say_in_is_refused(): void {
        // Permission is per course, so being allowed in one is not being allowed
        // in the next one along. Naming a course they are not in is refused
        // before the capability is even reached, because validate_context()
        // asks whether they may be there at all.
        $allowed = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $allowedcontext = \context_course::instance($allowed->id);
        $user = $this->getDataGenerator()->create_and_enrol($allowed, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/aimedia:use', CAP_ALLOW, $roleid, $allowedcontext->id);
        role_assign($roleid, $user->id, $allowedcontext->id);

        $this->setUser($user);
        $url = $this->recording((int) $user->id);

        $this->expectException(\moodle_exception::class);
        transcribe_editor_audio::execute(\context_course::instance($other->id)->id, $url);
    }

    public function test_being_in_a_course_is_not_being_allowed_in_it(): void {
        // Enrolled in the second course, so they may be there, and still refused:
        // this is the capability doing the work rather than enrolment.
        $allowed = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $allowedcontext = \context_course::instance($allowed->id);
        $user = $this->getDataGenerator()->create_and_enrol($allowed, 'editingteacher');
        $this->getDataGenerator()->enrol_user($user->id, $other->id, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/aimedia:use', CAP_ALLOW, $roleid, $allowedcontext->id);
        role_assign($roleid, $user->id, $allowedcontext->id);

        $this->setUser($user);
        $url = $this->recording((int) $user->id);

        $this->expectException(\required_capability_exception::class);
        transcribe_editor_audio::execute(\context_course::instance($other->id)->id, $url);
    }
}
