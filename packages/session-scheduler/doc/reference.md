# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Series and sessions

| Type | Role |
|---|---|
| `SessionScheduler` | The service: series, planning, sessions, attendance, substitutes, proposals and reminders. |
| `Series` | The schedule of a campaign: its first session, its rule, its game master, its players and the table's settings. |
| `Session` | One session: its start, minutes, occurrence, status, substitute and replies. |
| `SessionStatus` | Planned, cancelled or held. |
| `Reply` | Coming, absent, or absent later than the notice. |
| `SessionQuery` | Which sessions to read: of a series or of every series, in a window and a status. |
| `DateProposal` | Dates put to the members: for a new session or to replace one. |
| `Reminder` | A reminder due for a session, and the members to remind. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\SessionStore` | Keeps the series and sessions at a version, reads sessions by query and counts a member's sessions to come. |
| `Contract\SessionObserver` | Told of the events of a series. |
| `Contract\DateProposer` | Puts the dates of a proposal to the members. |
| `Store\InMemorySessionStore` | The series and sessions in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\ScheduleEvent` | Every event: the series. |
| `Event\SessionEvent` | The events of one session: the series and the session. |
| `Event\SeriesCreated` | A series was created. |
| `Event\SeriesChanged` | A series was changed. |
| `Event\DatesProposed` | Dates were put to the members. |
| `Event\SessionPlanned` | A session was planned or added. |
| `Event\SessionRescheduled` | A session moved. |
| `Event\SessionCancelled` | A session was cancelled. |
| `Event\SessionHeld` | A session took place. |
| `Event\AttendanceConfirmed` | A member is coming. |
| `Event\AbsenceAnnounced` | A player is absent. |
| `Event\GameMasterAbsent` | The game master who runs a session is absent. |
| `Event\SubstituteAssigned` | A substitute runs a session, or the series' game master again. |
| `Event\QuorumLost` | A session fell below the quorum. |
| `Event\QuorumRestored` | A session came back to the quorum. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\SchedulerException` | Every exception of the library. |
| `Exception\InvalidSchedule` | A value out of its shape or its bounds. |
| `Exception\ScheduleRefused` | A change the scheduler does not allow. |
| `Exception\ScheduleNotFound` | No series or session has the identifier. |
| `Exception\ScheduleConflict` | The series or session changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier, and lists of distinct members. |
| `Internal\Moment` | Instants compared and written by their seconds since the epoch. |
