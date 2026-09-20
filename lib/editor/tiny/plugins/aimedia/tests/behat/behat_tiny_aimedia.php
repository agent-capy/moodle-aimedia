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
     * Decide what the AI will answer, without there being an AI.
     *
     * Everything this plugin does after the answer arrives -- putting it in the alt
     * text, making that one press of undo, leaving the editor knowing it has changed --
     * needs an answer to arrive, and a test site has no AI to ask. So the one web
     * service call is answered here instead.
     *
     * This reaches into core/ajax, which the built module calls by property rather
     * than by a bound name, so replacing the property replaces what the module calls.
     * If that ever stops being true these scenarios fail rather than quietly stop
     * testing anything, which is the direction worth failing in.
     *
     * @Given /^the AI will answer "(?P<answer_string>(?:[^"]|\\")*)"$/
     * @Given /^the AI will (?P<never_string>never answer)$/
     * @param string $answer What it says, or "never answer" for a request left hanging.
     */
    public function the_ai_will_answer(string $answer): void {
        $this->require_tiny_tags();

        // A request that is never answered leaves the page as it looks in the middle,
        // which is what somebody saving their work then would save.
        $reply = $answer === 'never answer'
            ? 'new Promise(() => {})'
            : 'Promise.resolve({success: true, text: ' . json_encode($answer) . '})';

        // require() is asynchronous. Firing it and pressing the button would race, and
        // the race was lost every time: the real web service answered instead and the
        // scenario tested nothing. So a flag is set once it is really in place, and
        // the step waits for the flag.
        // Only this plugin's calls are answered here. Everything else on the page is
        // handed to the real one -- including core_get_string, which this very button
        // uses, and which answering with a made up reply breaks before the button ever
        // sends anything.
        $this->execute_script(
            "window.behatAimediaStubbed = false;"
            . " window.behatAimediaCalls = [];"
            . " require(['core/ajax'], (ajax) => {"
            . " const real = ajax.call;"
            . " ajax.call = (requests, ...rest) => requests.map((request) => {"
            . " window.behatAimediaCalls.push(request.methodname);"
            . " return request.methodname.indexOf('local_aimedia_') === 0"
            . " ? {$reply}"
            . " : real([request], ...rest)[0];"
            . " });"
            . " window.behatAimediaStubbed = true;"
            . " });"
        );

        $this->spin(function (): bool {
            if ($this->evaluate_script('return window.behatAimediaStubbed === true;') !== true) {
                throw new ExpectationException('The answer is not in place yet', $this->getSession());
            }

            return true;
        });
    }

    /**
     * Check what is in the editor's content, which is what a save would write.
     *
     * Read through getContent() rather than off the DOM, because the two are not the
     * same: an editor can be showing something it would not save, and this plugin
     * relies on that.
     *
     * @Then /^the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor content should( not)? contain "(?P<needle_string>(?:[^"]|\\")*)"$/
     * @param string $locator The editor.
     * @param string $needle What to look for.
     * @throws ExpectationException When the content does not say what it should.
     */
    public function the_editor_content_should_contain(string $locator, string $needle): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');
        $content = (string) $this->evaluate_javascript_for_editor($editorid, 'resolve(instance.getContent());');

        if (!str_contains($content, $needle)) {
            throw new ExpectationException(
                "Expected the saved content to contain '{$needle}', found '{$content}'",
                $this->getSession(),
            );
        }
    }

    /**
     * Check the editor knows whether it has been changed.
     *
     * Every "you have unsaved work" prompt in Moodle reads this, so a change the
     * editor does not know about is a change somebody loses.
     *
     * @Then /^the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor should be marked as changed$/
     * @param string $locator The editor.
     * @throws ExpectationException When it does not know.
     */
    public function the_editor_should_be_dirty(string $locator): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');
        $this->spin(
            function () use ($editorid): bool {
                $dirty = $this->evaluate_javascript_for_editor($editorid, 'resolve(instance.isDirty() ? 1 : 0);');
                if ((int) $dirty !== 1) {
                    throw new ExpectationException(
                        'The editor does not know it has been changed',
                        $this->getSession(),
                    );
                }

                return true;
            },
        );
    }

    /**
     * Press undo once, as somebody does when they do not like the answer.
     *
     * @When /^I undo once in the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor$/
     * @param string $locator The editor.
     */
    public function i_undo_once(string $locator): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');
        $this->execute_javascript_for_editor($editorid, 'instance.undoManager.undo();');
    }

    /**
     * Check the alt text of the picture, which is what the button writes.
     *
     * @Then /^the picture in the "(?P<locator_string>(?:[^"]|\\")*)" TinyMCE editor should be described as "(?P<alt_string>(?:[^"]|\\")*)"$/
     * @param string $locator The editor holding it.
     * @param string $alt What the alt text should say. "nothing" for an empty one.
     * @throws ExpectationException When it says something else.
     */
    public function the_picture_should_be_described_as(string $locator, string $alt): void {
        $this->require_tiny_tags();
        $editorid = $this->get_textarea_for_locator($locator)->getAttribute('id');
        $expected = $alt === 'nothing' ? '' : $alt;

        // Waited for rather than read once. The answer arrives from a web service, so
        // a step that looked at the page the instant the button was pressed would
        // pass whatever the code did.
        $this->spin(
            function () use ($editorid, $expected): bool {
                $found = (string) $this->evaluate_javascript_for_editor($editorid, <<<'JS'
                    const image = instance.getBody().querySelector('img');
                    resolve(image ? (image.getAttribute('alt') ?? '') : '(no picture)');
                JS);

                if ($found !== $expected) {
                    // What the page said as well as what the picture says. A step that
                    // only reported the alt text sent somebody hunting through the
                    // wrong half of this.
                    $calls = json_encode($this->evaluate_script('return window.behatAimediaCalls || [];'));
                    $said = trim((string) $this->evaluate_script(
                        "return (document.querySelector('.alert, .toast-message') || {}).innerText || '';"
                    ));
                    throw new ExpectationException(
                        "Expected the alt text to be '{$expected}', found '{$found}'."
                            . " Web service calls: {$calls}. Page said: '{$said}'",
                        $this->getSession(),
                    );
                }

                return true;
            },
        );
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
