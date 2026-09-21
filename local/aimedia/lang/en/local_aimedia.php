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

/**
 * Strings for component local_aimedia, language 'en'.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:describe_image'] = 'Ask about a picture';
$string['action:describe_image:defaultprompt'] = 'Describe this picture.';
$string['action:describe_image:description'] = 'Answer a question about a picture, in text.';
$string['action:describe_image:instruction'] = 'You are looking at a picture a user has supplied. Answer their question about it plainly and briefly. Say so if the picture does not show what is being asked about.';
$string['action:transcript_audio'] = 'Transcribe audio';
$string['action:transcript_audio:description'] = 'Turn a recording into text.';
$string['aimedia:use'] = 'Ask an AI about a recording or a picture';
$string['error:filetoolarge'] = 'That file is larger than this site will send for this action. The limit is {$a}. A shorter recording, or a smaller picture, will go through.';
$string['error:filetypenotaccepted'] = 'That kind of file cannot be used for this action. A recording has to be audio, and a description needs a picture.';
$string['error:nofile'] = 'No file arrived. Try uploading it again.';
$string['error:noprovider'] = 'No AI provider on this site can carry out either of these actions. An administrator has to enable one that can.';
$string['error:notadraftaudio'] = 'That recording is not one you have just made in this editor, so it cannot be sent. Record it again and try once more.';
$string['error:notadraftimage'] = 'That picture is not one you have just added to this editor, so it cannot be sent. Insert it again and try once more.';
$string['error:policynotaccepted'] = 'You have not yet accepted this site\'s AI usage policy. Open the AI media page once to read and accept it, then try again.';
$string['error:requestfailed'] = 'Something went wrong on the way to the AI service, and it did not say what. Try again, and tell an administrator if it keeps happening.';
$string['error:unknownaction'] = 'That is not an action this page offers.';
$string['form:action'] = 'What to do';
$string['form:file'] = 'Recording or picture';
$string['form:file_help'] = 'An audio file to transcribe, or an image to ask about. It is sent to whichever AI provider the site routes it to, and deleted from this site once the answer comes back.';
$string['form:question'] = 'Question';
$string['form:question_help'] = 'What to ask about the picture. Left empty, the model is asked to describe it. Recordings are transcribed and have nothing to ask.';
$string['form:submit'] = 'Send';
$string['intro'] = 'Upload a recording to have it transcribed, or a picture to ask a question about it. The file is sent to an AI provider and is not kept here afterwards.';
$string['pluginname'] = 'AI media actions';
$string['privacy:metadata:describe'] = 'What each question about a picture produced. The picture itself is not kept.';
$string['privacy:metadata:describe:filename'] = 'What the picture was called.';
$string['privacy:metadata:describe:generatedcontent'] = 'The answer the model gave about the picture.';
$string['privacy:metadata:describe:prompt'] = 'What the user asked about the picture.';
$string['privacy:metadata:describe:timecreated'] = 'When the question was asked.';
$string['privacy:metadata:transcript'] = 'What each transcription produced. The recording itself is not kept.';
$string['privacy:metadata:transcript:filename'] = 'What the recording was called.';
$string['privacy:metadata:transcript:timecreated'] = 'When the recording was transcribed.';
$string['privacy:metadata:transcript:transcript'] = 'The words the model heard in the recording.';
$string['privacy:metadata:userid'] = 'Who made the request.';
$string['result'] = 'Answer';
$string['result:about'] = 'From {$a->filename}, answered by {$a->model}.';
$string['task:purgesubmitted'] = 'Remove media that was sent to an AI and never cleaned up';
