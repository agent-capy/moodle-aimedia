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

use local_aimedia\aiactions\describe_image;
use local_aimedia\aiactions\transcript_audio;

/**
 * Tests for what the page offers and what it builds.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(media_request::class)]
final class media_request_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A file in the pool, to hand to an action.
     *
     * @param string $filename What it is called.
     * @return \stored_file The file.
     */
    protected function upload(string $filename): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aimedia',
            'filearea' => 'submitted',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => $filename,
        ], 'pretend this is media');
    }

    public function test_nothing_is_offered_when_no_provider_can_do_it(): void {
        // A site with no AI providers at all. Offering an action here would be an
        // option that fails after the file has been uploaded.
        $manager = \core\di::get(\core_ai\manager::class);

        $this->assertSame([], media_request::available($manager));
    }

    public function test_a_choice_becomes_the_action_it_names(): void {
        $file = $this->upload('lecture.mp3');

        $transcribe = media_request::make(
            transcript_audio::class,
            \context_system::instance()->id,
            3,
            $file,
            'ignored for a recording',
        );
        $describe = media_request::make(
            describe_image::class,
            \context_system::instance()->id,
            3,
            $file,
            'What is in this picture?',
        );

        $this->assertInstanceOf(transcript_audio::class, $transcribe);
        $this->assertInstanceOf(describe_image::class, $describe);
        $this->assertSame('What is in this picture?', $describe->get_question());
    }

    public function test_a_question_typed_for_a_recording_goes_nowhere(): void {
        // The form hides the field for a recording, but a form is not a promise:
        // the value can still arrive, and it must not end up anywhere.
        $action = media_request::make(
            transcript_audio::class,
            \context_system::instance()->id,
            3,
            $this->upload('seminar.mp3'),
            'this should be dropped',
        );

        $this->assertFalse(property_exists($action, 'prompttext'));
    }

    public function test_asking_nothing_still_asks_something(): void {
        // A picture with no question is a fair thing to send, and the action has
        // one of its own for that case rather than sending an empty prompt.
        $action = media_request::make(
            describe_image::class,
            \context_system::instance()->id,
            3,
            $this->upload('photo.jpg'),
        );

        $this->assertNotEmpty($action->get_question());
        $this->assertStringNotContainsString('[[', $action->get_question());
    }

    public function test_an_action_this_plugin_does_not_offer_is_refused(): void {
        $this->expectException(\coding_exception::class);

        media_request::make(
            \core_ai\aiactions\generate_text::class,
            \context_system::instance()->id,
            3,
            $this->upload('anything.mp3'),
        );
    }
}
