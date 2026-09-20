# AI audio actions for Moodle (`local_aiaudio`)

Defines an AI action Moodle does not have: **transcribe a recording**.

Moodle 5.0's AI subsystem has four actions and every one of them takes text in.
`generate_image` writes text and returns a picture; nothing asks an AI to listen.
This plugin adds `transcript_audio` so that providers which can transcribe have
something to declare, and so that the AI Router can route such a request.

It is a definition, not a feature: on its own it does nothing. A provider has to
offer the action, and something has to raise it.

## Status

- Component: `local_aiaudio`
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

## What is stored

`local_aiaudio_transcript`: the transcript, the recording's content hash, its
name and size, and when it happened. ⚠ **The recording itself is not kept.**
A transcript is the words somebody said, so the table names nobody.

## Licence

GNU GPL v3 or later.
