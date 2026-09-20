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
        $result = transcribe_editor_audio::execute($url);

        $this->assertFalse($result['success']);
        $this->assertSame('', $result['text']);
        $this->assertSame(get_string('error:notadraftaudio', 'local_aimedia'), $result['error']);
    }

    public function test_a_site_that_cannot_listen_says_so_rather_than_failing(): void {
        // No AI provider is installed in a test site, so this is the honest answer
        // and it has to arrive as an answer, not as an exception in the editor.
        $this->setAdminUser();
        $result = transcribe_editor_audio::execute($this->recording((int) get_admin()->id));

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('error:noprovider', 'local_aimedia'), $result['error']);
    }

    public function test_the_capability_is_what_allows_it(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $url = $this->recording((int) $user->id);

        $this->expectException(\required_capability_exception::class);
        transcribe_editor_audio::execute($url);
    }
}
