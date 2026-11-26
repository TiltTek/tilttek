# Changelog

All notable changes to `tilttek/handouts` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Handout`: a handout of a table under the application's reference, with a title of 1 to 150 characters and a text of
  1 to 20,000 characters, attachments, or both, and a revision that each edit raises.
- `Attachment`: a file of the application's storage named by its reference, with its media type and a description of 1
  to 500 characters; at most 50 distinct files per handout.
- `MediaTypes`: the media types an attachment may have — by default PNG, JPEG, WebP, AVIF and GIF images, PDF, MP3 and
  Ogg sounds, MP4 and WebM videos and plain text, SVG left out —, 1 to 100 chosen by the application.
- `Handouts`: creating, editing and removing a handout and reading a table's handouts; revealing to an `Audience` —
  everyone at the table or 1 to 500 players — and concealing again; `canSee()` and the audience as `Disclosure`
  entries; staging a reveal on a `Trigger`, at a moment or on a cue, as `StagedReveal` entries, withdrawing them,
  `releaseDue()` for the application's scheduler and `cue()` during play; erasing a player from every table. The
  application decides who may act and passes the acting reference, which the events carry.
- `Sighting`, `Visible` and `Freshness`: what each player has opened, at which revision and when; the handouts a player
  sees, the earliest revealed first, unseen, outdated or seen; the handouts they have not opened at their current
  revision.
- `Limits`: handouts per table, attachments per handout, audiences per handout and reveals per release or cue, by
  default 200, 10, 50 and 100.
- `Contract\HandoutStore`, with `Store\InMemoryHandoutStore`.
- `Contract\HandoutObserver`, told `HandoutChanged` with its `Change`, identifiers only.
- Exceptions under one `HandoutException` interface: `HandoutRefused` with its `Refusal`, `UnknownHandout` and
  `InvalidHandout`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
