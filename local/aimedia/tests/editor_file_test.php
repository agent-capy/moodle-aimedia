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
 * Tests for resolving the file behind a URL an editor is showing.
 *
 * The check that matters is whose draft it is. A draft area lives in its owner's
 * user context, and the URL carries that context, so anybody could name somebody
 * else's if this did not look. Both editor buttons go through here, so this is
 * the one place that check has to be right.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(editor_file::class)]
final class editor_file_test extends \advanced_testcase {
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
        ], 'pretend this is media');

        return $CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/42' . $filepath . $filename;
    }

    public function test_the_callers_own_draft_is_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $file = editor_file::resolve($this->draft_url((int) $user->id));

        $this->assertNotNull($file);
        $this->assertSame('diagram.png', $file->get_filename());
    }

    public function test_a_recording_is_found_the_same_way_a_picture_is(): void {
        // What the editor records lands in the same place under a different name,
        // so nothing about the resolving depends on which of the two it is.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $file = editor_file::resolve($this->draft_url((int) $user->id, 'recording-audio.ogg'));

        $this->assertNotNull($file);
        $this->assertSame('recording-audio.ogg', $file->get_filename());
    }

    public function test_a_draft_in_a_subfolder_is_found(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $file = editor_file::resolve($this->draft_url((int) $user->id, 'photo.jpg', '/holiday/'));

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
        $this->assertNull(editor_file::resolve($url));

        $this->setUser($owner);
        $this->assertNotNull(editor_file::resolve($url));
    }

    public function test_an_administrator_gets_no_exception_from_the_rule(): void {
        // Being able to do everything on the site is not a reason to have a
        // recording of somebody read out by a machine on their behalf.
        $owner = $this->getDataGenerator()->create_user();
        $url = $this->draft_url((int) $owner->id, 'recording-audio.ogg');

        $this->setAdminUser();

        $this->assertNull(editor_file::resolve($url));
    }

    public function test_the_same_file_is_found_when_the_site_turns_slash_arguments_off(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->draft_url((int) $user->id);

        // The setting exists for servers that cannot pass PATH_INFO, and core then
        // writes every file URL with the path in a parameter instead. It is the same
        // file and the same editor; accepting only one shape made the buttons do
        // nothing on those sites.
        $CFG->slasharguments = 0;
        $url = \moodle_url::make_draftfile_url(42, '/', 'diagram.png')->out(false);
        $this->assertStringContainsString('?file=', $url);

        $file = editor_file::resolve($url);

        $this->assertNotNull($file);
        $this->assertSame('diagram.png', $file->get_filename());
    }

    public function test_a_subfolder_survives_the_parameter_form_too(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->draft_url((int) $user->id, 'shot.png', '/screens/');

        $CFG->slasharguments = 0;
        $file = editor_file::resolve(
            \moodle_url::make_draftfile_url(42, '/screens/', 'shot.png')->out(false),
        );

        $this->assertNotNull($file);
        $this->assertSame('/screens/', $file->get_filepath());
    }

    public function test_somebody_elses_draft_is_refused_in_the_parameter_form_as_well(): void {
        global $CFG;

        $owner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($owner);
        $this->draft_url((int) $owner->id);
        $ownercontext = (int) \context_user::instance((int) $owner->id)->id;

        $this->setUser($other);
        $CFG->slasharguments = 0;

        // The shape of the URL must not become a way round whose draft area it names.
        $this->assertNull(editor_file::resolve(
            $CFG->wwwroot . '/draftfile.php?file=' . rawurlencode("/{$ownercontext}/user/draft/42/diagram.png"),
        ));
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
            $this->assertNull(editor_file::resolve($url), $why);
        }
    }

    public function test_a_draft_that_is_not_there_is_refused(): void {
        global $CFG;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contextid = \context_user::instance($user->id)->id;

        $this->assertNull(
            editor_file::resolve($CFG->wwwroot . '/draftfile.php/' . $contextid . '/user/draft/42/never-uploaded.png'),
        );
    }
}
