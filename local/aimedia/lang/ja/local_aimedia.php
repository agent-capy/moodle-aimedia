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
 * Strings for component local_aimedia, language 'ja'.
 *
 * @package    local_aimedia
 * @copyright  2026 UDAGAWA Mitsuru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action:describe_image'] = '画像について尋ねる';
$string['action:describe_image:defaultprompt'] = 'この画像を説明してください。';
$string['action:describe_image:description'] = '画像についての質問に、テキストで答えます。';
$string['action:describe_image:instruction'] = '利用者が渡した画像を見ています。それについての質問に、平易かつ簡潔に答えてください。画像が尋ねられている内容を写していない場合は、そう述べてください。';
$string['action:transcript_audio'] = '音声を文字起こしする';
$string['action:transcript_audio:description'] = '録音された音声をテキストに変換します。';
$string['aimedia:use'] = '録音や画像についてAIに尋ねる';
$string['error:filetoolarge'] = 'このアクションでこのサイトが送れる大きさを超えています。上限は {$a} です。録音を短くするか、画像を小さくすればお送りできます。';
$string['error:filetypenotaccepted'] = 'この種類のファイルは、このアクションには使えません。文字起こしには音声を、説明には画像をお使いください。';
$string['error:nofile'] = 'ファイルが届きませんでした。もう一度アップロードしてください。';
$string['error:noprovider'] = 'このサイトのAIプロバイダは、どちらのアクションも実行できません。実行できるものを管理者が有効にする必要があります。';
$string['error:notadraftaudio'] = 'その録音は、いまこのエディタで録音したものではないため送れません。録音し直してからもう一度お試しください。';
$string['error:notadraftimage'] = 'その画像は、いまこのエディタに追加したものではないため送れません。挿入し直してからもう一度お試しください。';
$string['error:policynotaccepted'] = 'このサイトのAI利用ポリシーにまだ同意していません。AI メディアの画面を一度開いて内容を確認し、同意してからもう一度お試しください。';
$string['error:unknownaction'] = 'この画面が提供していないアクションです。';
$string['form:action'] = '何をするか';
$string['form:file'] = '録音または画像';
$string['form:file_help'] = '文字起こしする音声ファイル、または質問したい画像です。サイトが振り分けたAIプロバイダへ送られ、応答が返ったらこのサイトからは削除されます。';
$string['form:question'] = '質問';
$string['form:question_help'] = '画像について尋ねたいことです。空にすると、説明を求めます。録音は文字起こしするだけなので、尋ねることはありません。';
$string['form:submit'] = '送信';
$string['intro'] = '録音をアップロードすると文字起こしします。画像をアップロードすると、それについての質問に答えます。ファイルはAIプロバイダへ送られ、こちらには残しません。';
$string['pluginname'] = 'AI メディアアクション';
$string['privacy:metadata:describe'] = '画像についての質問の結果です。画像そのものは保存しません。';
$string['privacy:metadata:describe:filename'] = '画像のファイル名。';
$string['privacy:metadata:describe:generatedcontent'] = 'モデルが画像について答えた内容。';
$string['privacy:metadata:describe:prompt'] = '利用者が画像について尋ねた内容。';
$string['privacy:metadata:describe:timecreated'] = '質問した日時。';
$string['privacy:metadata:transcript'] = '各文字起こしの結果です。録音そのものは保存しません。';
$string['privacy:metadata:transcript:filename'] = '録音のファイル名。';
$string['privacy:metadata:transcript:timecreated'] = '文字起こしを行った日時。';
$string['privacy:metadata:transcript:transcript'] = 'モデルが聞き取った内容。';
$string['privacy:metadata:userid'] = '要求した利用者。';
$string['result'] = '応答';
$string['result:about'] = '{$a->filename} について、{$a->model} が答えました。';
