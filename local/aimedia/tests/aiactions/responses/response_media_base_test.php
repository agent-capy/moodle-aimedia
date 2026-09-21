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

namespace local_aimedia\aiactions\responses;

/**
 * Tests that a failure can be reported on every supported release.
 *
 * Moodle 5.1 swapped which field a failed response must carry, so the same call
 * that works on one release is refused on another. Core updated its own response
 * classes; a plugin that declares an action owns its response class and has to
 * span the releases itself.
 *
 * These run against whichever Moodle they are executed on, so they say "whatever
 * this release insists on is present" rather than naming a field. The release
 * differences themselves are covered by dev/deploy/response-contract.sh, which
 * reads the response classes of every supported release without a Moodle.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(response_media_base::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(response_describe_image::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(response_transcript_audio::class)]
final class response_media_base_test extends \advanced_testcase {
    /**
     * Every response class this plugin declares.
     *
     * @return array<string, array{class-string}> The classes, named for the test output.
     */
    public static function response_classes(): array {
        return [
            'describe image' => [response_describe_image::class],
            'transcript audio' => [response_transcript_audio::class],
        ];
    }

    /**
     * What this release refuses to build a failure without.
     *
     * Read from the parent rather than from the version number, because the change
     * was backported. This mirrors the decision the class under test makes, on
     * purpose: if the parent grows a third arrangement, both have to be looked at.
     *
     * @param \core_ai\aiactions\responses\response_base $response A failed response.
     * @return string What the response carries to identify the fault.
     */
    private function identifying_detail(\core_ai\aiactions\responses\response_base $response): string {
        return method_exists($response, 'get_error')
            ? $response->get_error()
            : $response->get_errormessage();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_a_failure_given_only_a_message_is_accepted(string $classname): void {
        $response = new $classname(success: false, errorcode: 413, errormessage: 'Recording too large');

        $this->assertFalse($response->get_success());
        $this->assertSame(413, $response->get_errorcode());
        $this->assertNotEmpty($this->identifying_detail($response));
        $this->assertSame('Recording too large', $response->get_errormessage());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_a_failure_given_only_an_error_name_is_accepted(string $classname): void {
        // This is how core calls it from Moodle 5.1 onwards, where the message is
        // optional. Before this class spanned the releases, the name was not even
        // accepted and the failure could not be built at all.
        $response = new $classname(success: false, errorcode: 503, error: 'upstream_unavailable');

        $this->assertFalse($response->get_success());
        $this->assertSame(503, $response->get_errorcode());
        $this->assertNotEmpty($this->identifying_detail($response));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_a_failure_given_both_keeps_both(string $classname): void {
        $response = new $classname(
            success: false,
            errorcode: 503,
            error: 'upstream_unavailable',
            errormessage: 'The provider could not be reached.',
        );

        $this->assertSame('The provider could not be reached.', $response->get_errormessage());
        $this->assertNotEmpty($this->identifying_detail($response));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_a_failure_with_nothing_to_say_is_still_refused(string $classname): void {
        // Filling in one field from the other must not turn into filling in both
        // from nothing: a failure that names no fault is a bug in the caller.
        $this->expectException(\coding_exception::class);

        new $classname(success: false, errorcode: 500);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_a_success_needs_neither(string $classname): void {
        $response = new $classname(success: true);

        $this->assertTrue($response->get_success());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('response_classes')]
    public function test_the_router_can_answer_in_this_type(string $classname): void {
        // The router builds the response the action declares, by name, leaving out
        // the argument this release does not have. Whether that still works is the
        // thing these classes exist to keep true.
        if (!class_exists(\local_airouter\response_factory::class)) {
            $this->markTestSkipped('The AI router is not installed.');
        }

        $action = $classname === response_describe_image::class
            ? \local_aimedia\aiactions\describe_image::class
            : \local_aimedia\aiactions\transcript_audio::class;
        $response = \local_airouter\response_factory::failure(
            (new \ReflectionClass($action))->newInstanceWithoutConstructor(),
            503,
            'router_unavailable',
            'The AI router is not available.',
        );

        $this->assertInstanceOf($classname, $response);
        $this->assertFalse($response->get_success());
        $this->assertNotEmpty($this->identifying_detail($response));
    }
}
