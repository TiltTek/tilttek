# Changelog

All notable changes to `tilttek/activity` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Activity`: a member and an object as `phil/resource` references, a verb, a moment, a quantity from 1 to one
  billion and a context; `of()` from plain values, `sameGestureAs()` that leaves the moment out, `withoutObject()` and
  `toArray()`.
- `Verb`, `VerbPattern` and `Verbs`: verbs named by the application in dotted lower-case segments, patterns for one
  verb, a prefix (`game.*`) or every verb (`*`), and the declared verbs that refuse the others.
- `IdempotencyKey` and `Context`: the key that makes a gesture count once, without whitespace or control characters,
  and a few named facts about a gesture — integers, booleans and short strings, never a member's text —, sorted.
- `Entry` with `EntryKind`, `Appended`, `Totals` and `Erasure`: the lines of the journal at positions that only grow,
  recorded or cancelled, and what recording, counting and erasing returned.
- `Filter`: activities by member, verbs, object and period, `since` included and `until` excluded.
- `ActivityLog` and `InMemoryActivityLog`: append once by key and refuse the key for another gesture, cancel once, find,
  read after a position in order, the position of the last entry kept, totals without the cancelled activities, and the
  erasure of a member that keeps others' gestures with their object forgotten; `Reading` checks the bounds of a read.
  `InMemoryActivityLog::reset()` empties the journal, for a long-running process.
- `Journal`: record, cancel, find, read, count and erase, entries stamped by a PSR-20 clock, declared verbs checked
  before recording, a gesture dated within a window around the clock — thirty days before and five minutes after by
  default, the past unbounded when asked —, and `JournalObserver`s told of each new entry.
- `Projection`, `Checkpoints`, `InMemoryCheckpoints`, `Projections` and `Projector`: derived state handed the entries it
  has not seen in batches, its checkpoint moved after each batch and past what its filter leaves out, a rebuild of the
  whole journal or of a period from the later of two starts, the lag of a projection, and names checked and unique.
  `InMemoryCheckpoints::reset()` brings the checkpoints back to zero.
- `LiveProjections`: an observer that catches up the projections that read each new entry.
- `ActivityException`, implemented by `InvalidActivity`, `IdempotencyConflict`, `UnknownVerb`, `UnknownProjection`
  and `InvalidProjection`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
