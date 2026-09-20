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

namespace local_aimedia\task;

/**
 * Tests for clearing away recordings and pictures nothing came back for.
 *
 * The page deletes its own copy in a finally, which covers every way the request can
 * end badly except the ways that never reach it: a fatal error, a timeout, a browser
 * that gave up. This is for those, and the one mistake worth avoiding is taking a
 * file somebody is still waiting on.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(purge_submitted::class)]
final class purge_submitted_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Leave a copy behind, as an interrupted request does.
     *
     * @param int $itemid Which request it belonged to.
     * @param int $age How long ago it was made, in seconds.
     */
    protected function leave_behind(int $itemid, int $age): void {
        global $DB;

        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aimedia',
            'filearea' => purge_submitted::AREA,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => "recording{$itemid}.mp3",
        ], 'pretend this is audio');

        $DB->set_field('files', 'timecreated', time() - $age, ['id' => $file->get_id()]);
        // The directory record is stamped too, so that an area is judged by when its
        // request happened rather than by whichever row was written last.
        $DB->set_field_select(
            'files',
            'timecreated',
            time() - $age,
            'component = :component AND filearea = :filearea AND itemid = :itemid',
            ['component' => 'local_aimedia', 'filearea' => purge_submitted::AREA, 'itemid' => $itemid],
        );
    }

    /**
     * How many files are still held for a request.
     *
     * @param int $itemid Which request.
     * @return int The count, directory records included.
     */
    protected function held(int $itemid): int {
        global $DB;

        return $DB->count_records('files', [
            'component' => 'local_aimedia',
            'filearea' => purge_submitted::AREA,
            'itemid' => $itemid,
        ]);
    }

    public function test_a_copy_nothing_came_back_for_is_removed(): void {
        $this->leave_behind(1, purge_submitted::MAX_AGE + HOURSECS);

        ob_start();
        (new purge_submitted())->execute();
        ob_end_clean();

        $this->assertSame(0, $this->held(1));
    }

    public function test_a_request_that_may_still_be_running_is_left_alone(): void {
        // The one mistake worth avoiding. A file taken while somebody is waiting on
        // it turns a slow answer into no answer.
        $this->leave_behind(2, MINSECS);

        ob_start();
        (new purge_submitted())->execute();
        ob_end_clean();

        $this->assertGreaterThan(0, $this->held(2));
    }

    public function test_only_this_plugins_own_area_is_touched(): void {
        global $DB;

        // Somebody's draft, old enough to qualify if this looked at drafts. It is
        // theirs, and Moodle removes drafts on its own schedule.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $draft = get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance((int) $user->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 99,
            'filepath' => '/',
            'filename' => 'mine.mp3',
        ], 'pretend this is audio');
        $DB->set_field('files', 'timecreated', time() - YEARSECS, ['id' => $draft->get_id()]);

        ob_start();
        (new purge_submitted())->execute();
        ob_end_clean();

        $this->assertTrue($DB->record_exists('files', ['id' => $draft->get_id()]));
    }
}
