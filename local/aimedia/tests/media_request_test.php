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

    /**
     * A manager that returns the given response when an action is run through it.
     *
     * @param \core_ai\aiactions\responses\response_base $response What comes back.
     * @return \core_ai\manager The stand in.
     */
    protected function manager_returning(\core_ai\aiactions\responses\response_base $response): \core_ai\manager {
        $manager = $this->createStub(\core_ai\manager::class);
        $manager->method('process_action')->willReturn($response);

        return $manager;
    }

    /**
     * A manager that throws when an action is run through it.
     *
     * @param \Throwable $thrown What it throws.
     * @return \core_ai\manager The stand in.
     */
    protected function manager_throwing(\Throwable $thrown): \core_ai\manager {
        $manager = $this->createStub(\core_ai\manager::class);
        $manager->method('process_action')->willThrowException($thrown);

        return $manager;
    }

    public function test_a_provider_that_turns_the_request_down_is_a_message_not_a_crash(): void {
        // A provider says "no" by throwing, because core's own loop would otherwise
        // hand the request to the next provider. Being told no is an answer, and this
        // plugin shows it as one rather than as an error page.
        $refusal = '\local_airouter\exception\declined_request';
        if (!class_exists($refusal)) {
            $this->markTestSkipped('The AI Router is not installed on this site.');
        }

        $outcome = media_request::run(
            $this->manager_throwing(new $refusal('budget_exhausted', 'error:budgetexhausted')),
            media_request::make(
                class: transcript_audio::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $this->upload('recording.ogg'),
            ),
        );

        $this->assertFalse($outcome->success);
        $this->assertSame([], $outcome->data);
        $this->assertNotEmpty($outcome->error);
    }

    public function test_an_ordinary_failure_still_reads_the_same_way(): void {
        $response = $this->createStub(\core_ai\aiactions\responses\response_base::class);
        $response->method('get_success')->willReturn(false);
        $response->method('get_errormessage')->willReturn('The service is down');

        $outcome = media_request::run(
            $this->manager_returning($response),
            media_request::make(
                class: describe_image::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $this->upload('picture.png'),
            ),
        );

        $this->assertFalse($outcome->success);
        $this->assertSame('The service is down', $outcome->error);
    }

    public function test_a_failure_that_says_nothing_still_shows_something(): void {
        // From Moodle 5.1 the message on a failed response is optional: what the
        // release insists on is a short error name, which is for the log. Passing
        // that straight through would leave the person looking at an empty box.
        $response = $this->createStub(\core_ai\aiactions\responses\response_base::class);
        $response->method('get_success')->willReturn(false);
        $response->method('get_errormessage')->willReturn('');

        $outcome = media_request::run(
            $this->manager_returning($response),
            media_request::make(
                class: describe_image::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $this->upload('picture.png'),
            ),
        );

        $this->assertFalse($outcome->success);
        $this->assertSame(get_string('error:requestfailed', 'local_aimedia'), $outcome->error);
    }

    public function test_a_successful_answer_comes_back_whole(): void {
        $response = $this->createStub(\core_ai\aiactions\responses\response_base::class);
        $response->method('get_success')->willReturn(true);
        $response->method('get_response_data')->willReturn(['transcript' => 'What was said']);

        $outcome = media_request::run(
            $this->manager_returning($response),
            media_request::make(
                class: transcript_audio::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $this->upload('recording2.ogg'),
            ),
        );

        $this->assertTrue($outcome->success);
        $this->assertSame('What was said', $outcome->data['transcript']);
        $this->assertSame('', $outcome->error);
    }

    /**
     * A file of a chosen size and type, in the pool.
     *
     * @param string $filename What it is called.
     * @param int $bytes How big to make it.
     * @param string $mimetype What it claims to be.
     * @return \stored_file The file.
     */
    protected function upload_sized(string $filename, int $bytes, string $mimetype): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aimedia',
            'filearea' => 'submitted',
            'itemid' => 2,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ], str_repeat('x', $bytes));
    }

    /**
     * A manager that must never be asked to do anything.
     *
     * @return \core_ai\manager The stand in.
     */
    protected function manager_never_called(): \core_ai\manager {
        $manager = $this->createMock(\core_ai\manager::class);
        $manager->expects($this->never())->method('process_action');

        return $manager;
    }

    public function test_a_file_larger_than_the_limit_is_refused_before_anything_is_sent(): void {
        // The form states a limit, and the editor buttons never see a form. A provider
        // reads the whole file into memory to send it, so the size is checked on the
        // road they share rather than on the one screen that happens to have a form.
        $file = $this->upload_sized(
            'huge.png',
            media_limits::IMAGE_BYTES + 1,
            'image/png',
        );

        $outcome = media_request::run(
            $this->manager_never_called(),
            media_request::make(
                class: describe_image::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $file,
            ),
        );

        $this->assertFalse($outcome->success);
        $this->assertNotEmpty($outcome->error);
    }

    public function test_a_recording_may_be_larger_than_a_picture(): void {
        // A picture is base64 encoded into a JSON body and costs several times its
        // own size again; a recording is sent as multipart and copied once.
        $this->assertGreaterThan(media_limits::IMAGE_BYTES, media_limits::AUDIO_BYTES);
        $this->assertSame(media_limits::AUDIO_BYTES, media_limits::form_max_bytes());
    }

    public function test_a_recording_sent_as_a_picture_is_refused(): void {
        $file = $this->upload_sized('talk.ogg', 64, 'audio/ogg');

        $outcome = media_request::run(
            $this->manager_never_called(),
            media_request::make(
                class: describe_image::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $file,
            ),
        );

        $this->assertFalse($outcome->success);
        $this->assertSame(
            get_string('error:filetypenotaccepted', 'local_aimedia'),
            $outcome->error,
        );
    }

    public function test_a_file_within_the_limits_goes_through(): void {
        $response = $this->createStub(\core_ai\aiactions\responses\response_base::class);
        $response->method('get_success')->willReturn(true);
        $response->method('get_response_data')->willReturn(['generatedcontent' => 'A cat']);

        $outcome = media_request::run(
            $this->manager_returning($response),
            media_request::make(
                class: describe_image::class,
                contextid: \context_system::instance()->id,
                userid: (int) get_admin()->id,
                file: $this->upload_sized('small.png', 64, 'image/png'),
            ),
        );

        $this->assertTrue($outcome->success);
        $this->assertSame('A cat', $outcome->data['generatedcontent']);
    }

    public function test_the_form_offers_everything_any_action_accepts(): void {
        $offered = media_limits::accepted_types();

        foreach (media_request::ACTIONS as $class) {
            foreach (media_limits::groups($class) as $group) {
                $this->assertContains($group, $offered, $class);
            }
        }
    }
}
