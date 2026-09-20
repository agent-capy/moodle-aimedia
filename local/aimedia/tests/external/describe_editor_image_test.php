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
 * Tests for asking about a picture in the editor.
 *
 * Whose draft the picture is comes from editor_file and is tested there. What
 * is tested here is the rest: who may ask, and what the caller is told when no
 * provider on the site can look at a picture.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(describe_editor_image::class)]
final class describe_editor_image_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Put a picture in somebody's draft area and return the URL the editor shows.
     *
     * @param int $userid Whose draft area.
     * @return string The URL.
     */
    protected function picture(int $userid): string {
        global $CFG;

        $contextid = \context_user::instance($userid)->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 7,
            'filepath' => '/',
            'filename' => 'diagram.png',
        ], 'pretend this is a picture');

        return $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/7/diagram.png';
    }

    /**
     * Accept the site's AI usage policy for somebody, as the page lets them.
     *
     * @param int $userid Who accepts it.
     */
    protected function accept_policy(int $userid): void {
        \core_ai\manager::user_policy_accepted($userid, \context_system::instance()->id);
    }

    public function test_somebody_elses_picture_is_refused(): void {
        $owner = $this->getDataGenerator()->create_user();
        $url = $this->picture((int) $owner->id);

        $this->setAdminUser();
        $this->accept_policy((int) get_admin()->id);
        $result = describe_editor_image::execute(0, $url);

        $this->assertFalse($result['success']);
        $this->assertSame('', $result['text']);
        $this->assertSame(get_string('error:notadraftimage', 'local_aimedia'), $result['error']);
    }

    public function test_a_site_that_cannot_look_says_so_rather_than_failing(): void {
        $this->setAdminUser();
        $this->accept_policy((int) get_admin()->id);
        $result = describe_editor_image::execute(0, $this->picture((int) get_admin()->id));

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:noprovider', 'local_aimedia'), $result['error']);
    }

    public function test_the_capability_is_what_allows_it(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->accept_policy((int) $user->id);
        $url = $this->picture((int) $user->id);

        $this->expectException(\required_capability_exception::class);
        describe_editor_image::execute(0, $url);
    }

    public function test_a_teacher_may_ask_from_their_own_course(): void {
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/aimedia:use', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $teacher->id, $context->id);

        $this->setUser($teacher);
        $this->accept_policy((int) $teacher->id);
        $result = describe_editor_image::execute($context->id, $this->picture((int) $teacher->id));

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:noprovider', 'local_aimedia'), $result['error']);
    }

    public function test_the_ai_policy_must_be_accepted_before_anything_is_sent(): void {
        // Core does not check the policy on the way through, and a button is not a
        // place to assume somebody has read anything.
        $this->setAdminUser();
        $result = describe_editor_image::execute(0, $this->picture((int) get_admin()->id));

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:policynotaccepted', 'local_aimedia'), $result['error']);
    }
}
