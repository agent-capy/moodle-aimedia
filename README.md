# AI media actions for Moodle (`local_aimedia`)

Defines two AI actions Moodle does not have: **transcribe a recording** and
**ask about a picture**.

Moodle 5.0's AI subsystem has four actions and every one of them takes text in.
`generate_image` writes text and returns a picture; nothing asks an AI to listen,
and nothing asks it to look. This plugin adds `transcript_audio` and
`describe_image` so that providers which can do these things have something to
declare, and so that the AI Router can route such a request.

It is a definition, not a feature: on its own it does nothing. A provider has to
offer the action, and something has to raise it. This plugin also ships the two
smallest things that raise them — a page and an editor button.

## Status

- Components: `local_aimedia` (the actions) and `tiny_aimedia` (the editor buttons)
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

**Until that changes, stop these actions with a routing rule rather than with the
switch.** This has been written up for core.

⚠ An action added to a site after a provider instance was created is listed on
that instance but not enabled, because `actionconfig` is written once at
creation. There is no way to fix that from the settings screen;
`update_provider_instance()` adds the missing keys without touching the rest.

## In the editor

`tiny_aimedia` adds two buttons to TinyMCE. Each is enabled only while something
it can work on is selected, and each is offered as a toolbar button and a menu
item under **Tools**.

| Select | Press | What happens |
| --- | --- | --- |
| An image you have just inserted | **Describe this image** | The answer becomes the image's **alt text** |
| A recording you have just made | **Transcribe this recording** | The words are inserted **after the player**, as a paragraph |

Alt text is why the first is worth having. A teacher who has just dropped a
diagram into a page knows what it shows and writes nothing, because writing it
out is dull; the model is good at exactly that, and the person stays in charge of
the result.

The second is the other half of a feature Moodle already has. Moodle can record
straight into the editor, and what it leaves behind is a player: nothing to
search, nothing to quote, and nothing at all for somebody who cannot hear it.
The words go into the page next to the recording, where the author can read them
over and correct them before anybody else sees them.

⚠ **Only files you have just added.** A button sends the file behind the image or
the player, and only a draft file of your own can be resolved to one — a file
already saved into a page belongs to whichever component saved it, and whether
you may send it is that component's judgement, not this plugin's.

⚠ What a model heard is inserted as **text, never as markup**.

⚠ The site's **AI usage policy** has to be accepted before either button sends
anything. Core does not check it on the way through, so the buttons check it
themselves; the page is where somebody reads and accepts it.

The buttons are offered only in editors that can hold files, and only to people
with `local/aimedia:use`.

⭐ **Each request carries the context the editor is in.** That is what makes a
routing rule about a course apply to it, and what puts it in that course's usage
report. A request that said nothing about where it came from would match no rule
and be reported against nothing.

## The page

`/local/aimedia/index.php` — upload a recording to have it transcribed, or a
picture to ask a question about it. Granted by `local/aimedia:use`, which nobody
has until it is given: sending a recording or a picture to an AI costs the site
money and sends somebody's voice or face outside it.

It is reached from **the course**, where people teaching are, and from the site
navigation for anybody allowed to use it site-wide. Opened from a course it
takes `?contextid=`, and the request is made in that course.

⭐ The capability is declared at course level, so it can be given to a role that
is assigned in a course — a teacher's role is. Declared at site level it could
only be given to a role assigned site-wide, which means giving it to everybody.

### Who may use it

| Role | Out of the box |
| --- | --- |
| Manager, teacher, non-editing teacher | ✅ **Allowed**, as with the AI placements Moodle ships |
| Student | Not allowed |
| Anybody else | Not allowed |

Students are left out rather than refused on principle: a picture or a recording
is somebody's face or voice, and each request costs the site money, so letting a
whole cohort send them is a decision for the site. One tick adds them.

⚠ Moodle applies the defaults in `db/access.php` only when a capability is first
installed. A site that already had this one gets them from an upgrade step
instead, which **leaves alone any role somebody has already decided about**.

It exists because the editor buttons only reach what is in an editor, and because
it is the smallest thing that exercises the whole chain. The file is deleted from
the site once the answer comes back.

⚠ The site's AI usage policy is shown and has to be accepted first, the same way
the placements Moodle ships require it.

⚠ Only actions some enabled provider can carry out are offered. An action nothing
can perform would be an option that fails after the upload, which is the worst
moment to find out.

## What is stored

`local_aimedia_transcript`: the transcript, the recording's content hash, its
name and size, who asked, where, and when. `local_aimedia_describe`: the
question, the answer, and the same about the picture.

⚠ **Neither the recording nor the picture is kept**, only its content hash.

These tables held no user id to begin with, on the reasoning that a transcript
names nobody. That was wrong: core's action register points at these rows and
carries the user, so they were reachable from a person while being invisible to
every privacy request. They now hold the user and the context themselves, which
also means export and deletion do not depend on core's register still being
there when this plugin's privacy provider runs.

⭐ Everything here is exported and deleted through Moodle's Privacy API.

## Licence

GNU GPL v3 or later.
