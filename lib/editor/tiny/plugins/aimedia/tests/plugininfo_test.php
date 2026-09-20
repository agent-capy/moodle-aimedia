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

namespace tiny_aimedia;

/**
 * Tests for who is offered the buttons.
 *
 * The answer has to be the same one the server gives when the button is pressed. The
 * capability is declared at course level and given to teachers, so it is a question
 * about the context the editor is in, and asking it anywhere else answers about
 * somebody else.
 *
 * @package    tiny_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(plugininfo::class)]
final class plugininfo_test extends \advanced_testcase {
    /** @var array An editor that can hold files, which both buttons need. */
    protected const WITH_FILES = ['maxfiles' => 10];

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_a_teacher_is_offered_the_buttons_in_their_own_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        // The permission comes from the course, which is where teachers are. Asked at
        // the site it reads as false, and the buttons were never drawn for the people
        // the feature was written for.
        $this->assertTrue(plugininfo::is_enabled(
            \context_course::instance((int) $course->id),
            self::WITH_FILES,
            [],
        ));
    }

    public function test_the_same_teacher_is_not_offered_them_somewhere_else(): void {
        $theirs = $this->getDataGenerator()->create_course();
        $elsewhere = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($theirs, 'editingteacher');
        $this->setUser($teacher);

        $this->assertFalse(plugininfo::is_enabled(
            \context_course::instance((int) $elsewhere->id),
            self::WITH_FILES,
            [],
        ));
    }

    public function test_a_student_is_not_offered_them(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->assertFalse(plugininfo::is_enabled(
            \context_course::instance((int) $course->id),
            self::WITH_FILES,
            [],
        ));
    }

    public function test_an_editor_that_cannot_hold_files_has_nothing_to_work_on(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        // Both buttons act on a file the person has just put in the editor.
        $this->assertFalse(plugininfo::is_enabled(
            \context_course::instance((int) $course->id),
            ['maxfiles' => 0],
            [],
        ));
    }
}
