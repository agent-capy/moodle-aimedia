# AI audio actions for Moodle (`local_aimedia`)

Defines an AI action Moodle does not have: **transcribe a recording**.

Moodle 5.0's AI subsystem has four actions and every one of them takes text in.
`generate_image` writes text and returns a picture; nothing asks an AI to listen.
This plugin adds `transcript_audio` so that providers which can transcribe have
something to declare, and so that the AI Router can route such a request.

It is a definition, not a feature: on its own it does nothing. A provider has to
offer the action, and something has to raise it.

## Status

- Component: `local_aimedia`
- Required Moodle version: `2025041400` (Moodle 5.0.0 or later)
- Maturity: `MATURITY_ALPHA`

## ⚠ What core does with an action it did not define

Three places in `core_ai\manager` build an action's class name as
`core_ai\aiactions\` plus its basename, so an action defined anywhere else is not
found there. Verified on Moodle 5.0.9:

| | |
| --- | --- |
| Being used | **Works.** A provider instance is created with its action config keyed by the class names the provider lists, and the action arrives switched on |
| The enable/disable switch | ⚠ **Does not work.** The switch writes to `core_ai\aiactions\transcript_audio`, which nothing reads, so it bounces back. **The action cannot be switched off from that screen** |
| Its display name | Works, because core asks the action through `get_name()`, which is overridable |
| The message after using the switch | Says `[[action_transcript_audio]]`, because that string is looked up in `core_ai` |

⭐ Core already has the method that would fix the first of these —
`base::get_response_classname()` — and `manager.php` rebuilds the name by hand
instead of calling it.

**Until that changes, stop this action with a routing rule rather than with the
switch.** This has been written up for core.

## The page

`/local/aimedia/index.php` — upload a recording to have it transcribed, or a
picture to ask a question about it. Granted by `local/aimedia:use`, which nobody
has until it is given: sending a recording or a picture to an AI costs the site
money and sends somebody's voice or face outside it.

It is a page rather than a placement because Moodle's placements put AI into text
somebody is already editing, and there is nowhere in Moodle that a person hands
over a file and expects words back. It is the smallest thing that exercises the
whole chain, and the file is deleted from the site once the answer comes back.

⚠ The site's AI usage policy is shown and has to be accepted first, the same way
the placements Moodle ships require it.

⚠ Only actions some enabled provider can carry out are offered. An action nothing
can perform would be an option that fails after the upload, which is the worst
moment to find out.

## What is stored

`local_aimedia_transcript`: the transcript, the recording's content hash, its
name and size, and when it happened. ⚠ **The recording itself is not kept.**
A transcript is the words somebody said, so the table names nobody.

## Licence

GNU GPL v3 or later.
