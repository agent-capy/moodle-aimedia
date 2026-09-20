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

namespace local_aiaudio\aiactions;

use local_aiaudio\aiactions\responses\response_transcript_audio;

/**
 * Tests for the transcribe audio action.
 *
 * @package    local_aiaudio
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(transcript_audio::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(response_transcript_audio::class)]
final class transcript_audio_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A recording stored in the file area, to hand to the action.
     *
     * @param string $contents What the file holds.
     * @param string $filename What it is called.
     * @return \stored_file The file.
     */
    protected function recording(string $contents = 'not really audio', string $filename = 'lecture.mp3'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aiaudio',
            'filearea' => 'draft',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => $filename,
        ], $contents);
    }

    public function test_the_action_names_itself_without_help_from_core(): void {
        // Core resolves an action's display name through get_name(), which is
        // overridable, so this one reads properly even though core has no string
        // for it. The notification after using the enable switch does not, but
        // that switch does not work for a custom action anyway.
        $this->assertSame('transcript_audio', transcript_audio::get_basename());
        $this->assertStringNotContainsString('[[', transcript_audio::get_name());
        $this->assertStringNotContainsString('[[', transcript_audio::get_description());
    }

    public function test_the_action_says_which_class_carries_its_answer(): void {
        // Core builds this name from its own namespace in manager.php rather than
        // asking, so overriding it changes nothing today. It is overridden so that
        // the day core asks, the answer is already right.
        $this->assertSame(
            response_transcript_audio::class,
            transcript_audio::get_response_classname(),
        );
    }

    public function test_a_transcript_is_stored_without_the_recording(): void {
        global $DB;

        $file = $this->recording('not really audio', 'seminar.mp3');
        $action = new transcript_audio(
            contextid: \context_system::instance()->id,
            userid: 5,
            file: $file,
            language: 'ja',
        );

        $response = new response_transcript_audio(success: true);
        $response->set_response_data([
            'transcript' => 'こんにちは',
            'durationms' => 4200,
            'model' => 'whisper-large-v3-turbo',
        ]);

        $id = $action->store($response);
        $record = $DB->get_record('local_aiaudio_transcript', ['id' => $id]);

        $this->assertSame('こんにちは', $record->transcript);
        $this->assertSame('seminar.mp3', $record->filename);
        $this->assertSame('ja', $record->language);
        $this->assertEquals(4200, $record->durationms);
        // The recording is identified, not kept. A voice is not this table's to hold.
        $this->assertSame($file->get_contenthash(), $record->contenthash);
        $this->assertObjectNotHasProperty('filecontents', $record);
    }

    public function test_the_recording_can_be_read_more_than_once(): void {
        // The router offers a failed request to the next candidate, so whatever
        // the action carries has to survive being read twice. A stream would not.
        $file = $this->recording('the same bytes both times');
        $action = new transcript_audio(
            contextid: \context_system::instance()->id,
            userid: 5,
            file: $file,
        );

        $first = $action->get_configuration('file')->get_content();
        $second = $action->get_configuration('file')->get_content();

        $this->assertSame('the same bytes both times', $first);
        $this->assertSame($first, $second);
    }

    public function test_a_failure_carries_a_code_and_a_message(): void {
        // Core refuses a failed response that has neither, with a coding exception
        // rather than an error the user could act on.
        $response = new response_transcript_audio(
            success: false,
            errorcode: 413,
            errormessage: 'Recording too large',
        );

        $this->assertFalse($response->get_success());
        $this->assertSame(413, $response->get_errorcode());
        $this->assertNull($response->get_response_data()['transcript']);
    }
}
