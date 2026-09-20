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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use Behat\Mink\Exception\ExpectationException;

require_once(__DIR__ . '/../../../../../../behat/behat_base.php');
require_once(__DIR__ . '/../../../../tests/behat/editor_tiny_helpers.php');

/**
 * Steps for the things only a real browser can do to this plugin.
 *
 * Pasting a picture is one of them. What the editor holds after a paste is not a file:
 * it is a blob in memory, and the address on the image means nothing to the server
 * until Moodle has uploaded it. Nothing in a unit test or a page-level acceptance test
 * reaches that state, which is why the case it broke in was found by hand.
 *
 * @package    tiny_aimedia
 * @category   test
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tiny_aimedia extends behat_base {
    use editor_tiny_helpers;

    /** @var string A one pixel PNG, as small as a picture gets. */
    protected const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Accept the site's AI usage policy on somebody's behalf.
     *
     * The buttons refuse to send anything until it has been accepted, and they refuse
     * before they look at the picture, so without this a scenario about the picture
     * never reaches the picture.
     *
     * @Given /^"(?P<username_string>(?:[^"]|\\")*)" has accepted the AI usage policy$/
     * @param string $username Whose acceptance to record.
     */
    public function user_has_accepted_the_ai_policy(string $username): void {
        $user = \core_user::get_user_by_username($username, 'id', null, MUST_EXIST);
        \core_ai\manager::user_policy_accepted((int) $user->id, \context_system::instance()->id);
    }

    /**
     * Paste a picture into an editor, the way somebody pastes a screenshot.
     *
     * Built as a clipboard event carrying a file, which is what the browser delivers
     * when the clipboard holds an image. TinyMCE answers it by putting the picture in
     * its blob cache and inserting an img whose src is a blob: address.
     *
     * @When /^I paste a picture into the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor$/
     * @param string $locator The editor to paste into.
     */
    public function i_paste_a_picture(string $locator): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');
        $pixel = self::PIXEL;

        $this->execute_javascript_for_editor($editorid, <<<JS
            const binary = atob('{$pixel}');
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], 'pasted.png', {type: 'image/png'}));
            instance.focus();
            instance.getBody().dispatchEvent(new ClipboardEvent('paste', {
                clipboardData: transfer,
                bubbles: true,
                cancelable: true,
            }));
        JS);
    }

    /**
     * Put the cursor on the picture, as somebody does before pressing the button.
     *
     * @When /^I select the pasted picture in the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor$/
     * @param string $locator The editor holding it.
     */
    public function i_select_the_pasted_picture(string $locator): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');

        $this->execute_javascript_for_editor($editorid, <<<'JS'
            const image = instance.getBody().querySelector('img');
            if (image) {
                instance.selection.select(image);
                instance.nodeChanged();
            }
        JS);
    }

    /**
     * Check what the picture's address looks like at this moment.
     *
     * The whole of the bug this exists for is in the address, so the step says what it
     * expects the address to look like rather than only that a picture is there.
     *
     * @Then /^the picture in the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor should be a "(?P<kind_string>(?:[^"]|\\")*)"$/
     * @param string $locator The editor holding it.
     * @param string $kind One of "blob", "draft file" or "cache busted address".
     * @throws ExpectationException When the address is not of that kind.
     */
    public function the_picture_should_be(string $locator, string $kind): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');

        $src = (string) $this->evaluate_javascript_for_editor($editorid, <<<'JS'
            const image = instance.getBody().querySelector('img');
            resolve(image ? image.getAttribute('src') : '');
        JS);

        $expected = match ($kind) {
            'blob' => 'blob:',
            'draft file' => '/draftfile.php',
            // Said out loud because it is the whole of what went wrong, and because
            // nothing on the screen shows it.
            'cache busted address' => '?',
            default => throw new ExpectationException("Unknown kind of address: {$kind}", $this->getSession()),
        };

        if (!str_contains($src, $expected)) {
            throw new ExpectationException(
                "Expected a {$kind} address containing '{$expected}', found '{$src}'",
                $this->getSession(),
            );
        }
    }
}
