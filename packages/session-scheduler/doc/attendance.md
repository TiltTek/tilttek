# Attendance and reminders

## Coming and absent

A member answers for a session with `confirm($session, $member)` or `absent($session, $member)`: a player of the series
or the game master who runs the session — the substitute when one is assigned. The session is planned and has not
started. Answering again the same way changes nothing; a member changes their answer by giving the other one.

| Reply | Meaning |
|---|---|
| none | The member has not answered: they count as coming for the quorum. |
| `Reply::Coming` | They said they are coming. |
| `Reply::Absent` | They said they are absent at least the series' notice before the session. |
| `Reply::AbsentLate` | They said it later than the notice: recorded all the same, for the game master to decide. |

An absence is late when the moment it is announced is after the session's start less the notice; announced exactly at
the notice, it is not late. The game master who runs the session says they are absent too: the session then waits for a
substitute, or to be cancelled, and the observer is told `GameMasterAbsent`.

## The quorum

The players not absent from a session are counted against the series' quorum. When an absence brings them below it,
the observer is told `QuorumLost`; when a player coming brings them back to it, `QuorumRestored`. Each is told once, at
the change. The session is never cancelled on its own: the game master decides.

## The cap of upcoming sessions

`SessionScheduler` takes `maxComing`, 1 to 100: the most planned sessions starting after now a member may say they are
coming to, in every series. A member at the cap is refused; confirming a session they already come to is not counted
again. Without it, there is no cap.

## Proposing dates

`propose($series, $candidates, $by)` puts 1 to 20 distinct starts after now to the game master and the players, for a
first session or one to add. `proposeReplacement($session, $through, $by, $count = 7)` puts the days from tomorrow
through a moment, at the series' time of day on its wall clock, on which no other planned session of the series starts
— 1 to 20 of them, from at most 366 days walked — to replace a planned or cancelled session; a held session is not
replaced.

Both build a `DateProposal`, its dates the earliest first, give it to the `Contract\DateProposer` and return it. The
answer comes back later: the application calls `add()` or `reschedule()` with the date chosen.

## Reminders

`reminders($after, $through)` lists the reminders due after one moment through another, a window of at most 31 days,
the earliest due first. A reminder is due the series' `reminder` hours before a planned session; a series without one
has none. Each `Reminder` names the series, the session, its start, when the reminder is due, and the members to
remind: the game master who runs the session, then the players, without those who said they are absent.

The scheduler keeps nothing between calls: the application's scheduled task passes the end of its previous call as
the start of the next, so that each reminder is due once.
