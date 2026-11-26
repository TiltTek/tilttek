# Changelog

All notable changes to `tilttek/session-scheduler` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Series`: the schedule of a campaign — its first session in its time zone, an RFC 5545 rule read by
  `phil/recurrence`, its game master, up to 50 players, the minutes of a session, the quorum, the notice of an absence
  (24 hours by default) and one reminder or none — bounded and checked.
- `Session`, `SessionStatus` and `Reply`: one session, the occurrence it stands for, whether it is off calendar, its
  status, its substitute game master and the members' replies.
- `SessionScheduler`: create a series, and change it by its game master, who may hand it over; it keeps no state
  between calls and reads the time from a PSR-20 clock.
- `SessionScheduler::plan()`: the sessions of the rule's occurrences planned in batches of up to 100, idempotent, an
  occurrence already planned, rescheduled or cancelled never planned again.
- `SessionScheduler::add()`: a session at any start after now, marked off calendar when it is not an occurrence of the
  rule, never refused for it.
- `SessionScheduler::reschedule()` and `postpone()`: a session moved, or a session and every later one moved by the
  same days on their wall clock so that their spacing stays, the replies dropped.
- `SessionScheduler::cancel()`, `hold()` and `substitute()`: a session cancelled, held once started, or run by a
  substitute game master who is not a player.
- `SessionScheduler::confirm()` and `absent()`: a member coming or absent, an absence later than the notice recorded
  and marked late, the game master who runs the session absent, and an optional cap of upcoming sessions per member.
- The quorum: the game master told when absences bring the players below it and when it comes back, the session never
  cancelled on its own.
- `DateProposal`, `Contract\DateProposer`, `SessionScheduler::propose()` and `proposeReplacement()`: dates for a first
  session, or the free days at the series' time of day to replace one, put to the members.
- `Reminder` and `SessionScheduler::reminders()`: the reminders due in a window of up to 31 days, to the members not
  absent.
- `SessionQuery` and `SessionScheduler::sessions()`: the sessions of a series or of every series, in a window and a
  status, the earliest first.
- The events `SeriesCreated`, `SeriesChanged`, `DatesProposed`, `SessionPlanned`, `SessionRescheduled`,
  `SessionCancelled`, `SessionHeld`, `AttendanceConfirmed`, `AbsenceAnnounced`, `GameMasterAbsent`,
  `SubstituteAssigned`, `QuorumLost` and `QuorumRestored`, by identifiers only, told once to a
  `Contract\SessionObserver`.
- `Contract\SessionStore`, kept at a version, read by query and counting a member's sessions to come, and
  `Store\InMemorySessionStore` for tests.
- The exceptions `InvalidSchedule`, `ScheduleRefused`, `ScheduleNotFound` and `ScheduleConflict`, under
  `SchedulerException`.
- The adapter of a scheduling poll, documented.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
