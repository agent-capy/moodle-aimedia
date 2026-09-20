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
 * Tests for resolving an image the editor is showing.
 *
 * The check that matters is whose draft it is. A draft area lives in its owner's
 * user context, and the URL carries that context, so anybody could name somebody
 * else's if this did not look.
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
     * Put a file in somebody's draft area and return the URL the editor would show.
     *
     * @param int $userid Whose draft area.
     * @param string $filename What the file is called.
     * @param string $filepath Where in the draft area it sits.
     * @return string The URL.
     */
    protected function draft_url(int $userid, string $filename = 'diagram.png', string $filepath = '/'): string {
        global $CFG;

        $contextid = \context_user::instance($userid)->id;
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 42,
            'filepath' => $filepath,
            'filename' => $filename,
        ], 'pretend this is a picture');

        return $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/42' . $filepath . $filename;
    }

    /**
     * Call the protected resolver.
     *
     * @param string $url The URL to resolve.
     * @return \stored_file|null What it resolved to.
     */
    protected function resolve(string $url): ?\stored_file {
        $method = (new \ReflectionClass(describe_editor_image::class))->getMethod('draft_file');
        $method->setAccessible(true);

        return $method->invoke(null, $url);
    }

    public function test_the_callers_own_draft_is_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $file = $this->resolve($this->draft_url((int) $user->id));

        $this->assertNotNull($file);
        $this->assertSame('diagram.png', $file->get_filename());
    }

    public function test_a_draft_in_a_subfolder_is_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $file = $this->resolve($this->draft_url((int) $user->id, 'photo.jpg', '/holiday/'));

        $this->assertNotNull($file);
        $this->assertSame('/holiday/', $file->get_filepath());
    }

    public function test_somebody_elses_draft_is_refused(): void {
        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $url = $this->draft_url((int) $owner->id);

        // The file exists and the URL is well formed. The only thing wrong with it
        // is whose it is, and that is the whole point of the check.
        $this->setUser($other);
        $this->assertNull($this->resolve($url));

        $this->setUser($owner);
        $this->assertNotNull($this->resolve($url));
    }

    public function test_anything_that_is_not_a_draft_url_is_refused(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \context_user::instance($user->id)->id;

        $refused = [
            'a saved file' => $CFG->wwwroot . '/pluginfile.php/' . $contextid . '/user/private/0/x.png',
            'another site' => 'https://example.invalid/draftfile.php/' . $contextid . '/user/draft/42/x.png',
            'not a draft area' => $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/private/42/x.png',
            'an external image' => 'https://example.invalid/x.png',
            'a data uri' => 'data:image/png;base64,AAAA',
            'too few parts' => $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft',
        ];

        foreach ($refused as $why => $url) {
            $this->assertNull($this->resolve($url), $why);
        }
    }

    public function test_a_draft_that_is_not_there_is_refused(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \context_user::instance($user->id)->id;

        $this->assertNull(
            $this->resolve($CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/42/never-uploaded.png'),
        );
    }
}
