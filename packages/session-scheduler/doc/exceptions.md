# Exceptions

Every exception of the library implements `Exception\SchedulerException`: catch it to catch them all.

| Exception | Raised when |
|---|---|
| `Exception\InvalidSchedule` | A value is out of its shape or its bounds: an identifier, the players, the minutes, the quorum, the notice, the reminder, a version, the replies, the dates of a proposal, a limit, a count, the days of a postponement, the cap, a window that ends before it starts or a window of reminders longer than 31 days. |
| `Exception\ScheduleRefused` | The scheduler does not allow the change: a new series is not at version 0 or has the identifier of a kept one, the member is not the series' game master, a change moves a series to another campaign, a start is past, a session stands for that start already, the session is not planned or has started, a session is held before it starts or by someone who runs neither it nor its series, a substitute is a player, the member neither plays nor runs the session, the member reached the cap, a held session is replaced, or no day is free for a replacement. |
| `Exception\ScheduleNotFound` | No series or no session has the identifier. |
| `Exception\ScheduleConflict` | The series or session changed since it was read: read it again and make the change anew. |

A rule `phil/recurrence` cannot read raises its `InvalidRule` before a series is built. Nothing is kept when an
exception is raised, except by `plan()` and `postpone()`, which keep each session as they go: a conflict on one leaves
those before it kept, and calling again goes on.
