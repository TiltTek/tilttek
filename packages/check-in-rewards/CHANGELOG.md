# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/check-in-rewards` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `AttendanceRecorder`, called with a scan's identifier from a listener of the `CheckInRecorded` event of
  `phil/checkin-bundle` or from a message: an admission — outcome admitted, no conflict —, read as the check-in ledger
  of `phil/checkin` keeps it, recorded once in the journal of `tilttek/activity`
  under the scan's identifier, with the first admission of the member to the session, on the day, at the place and in
  the week, each once under a key drawn from what makes it unique; days and ISO weeks in the event's timezone; a
  moment never later than the server's reception.
- `Contract\PresenceResolver` and `Presence`: what the application says of a check-in — member, session, event, place,
  track, timezone —, null for an admission that rewards no one, such as an erased member.
- `AttendanceVerbs`: the verbs the library records, and their pattern `attendance.*`.
- `AttendanceCriterion`: sessions — a convention passport —, every day of an event, distinct places, one place
  discovered and weeks attended without penalty for a week missed, written for the `CriterionReader` of
  `tilttek/badges` and the `QuestReader` of `tilttek/quests`.
- `AttendanceReader` and `AttendanceSummary`: a member's distinct sessions, days, places and weeks, read by that member
  only; no ranking of attendance.
- `Exception\CheckInRewardsException`, `InvalidPresence`, `InvalidCriterion` and `AccessDenied`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
