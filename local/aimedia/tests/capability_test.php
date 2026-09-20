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

namespace local_aimedia;

/**
 * Tests for who may use this out of the box.
 *
 * Who holds a capability on a fresh site is a decision, and a decision that is
 * only written in db/access.php is one nothing checks. It is checked here as it
 * is on a real site: by asking about a person in a course.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class capability_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Who holds it in a course without anybody changing anything.
     *
     * @return array<string, array{string, bool}> Role archetype, and whether it holds it.
     */
    public static function roles(): array {
        return [
            'a manager' => ['manager', true],
            'a teacher who may edit' => ['editingteacher', true],
            'a teacher who may not' => ['teacher', true],
            // Each request is somebody's face or voice and costs the site money,
            // so a whole cohort sending them is for the site to decide.
            'a student' => ['student', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_who_may_use_it_out_of_the_box(string $archetype, bool $allowed): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, $archetype);

        $this->assertSame(
            $allowed,
            has_capability('local/aimedia:use', \context_course::instance($course->id), $user),
        );
    }
}
