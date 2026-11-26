# Series and sessions

## A series

A `Series` is the schedule of a campaign:

| Value | Bounds | Meaning |
|---|---|---|
| `id` | an identifier of at most 96 characters | the series; its sessions' identifiers add their instant to it |
| `campaign` | an identifier | the campaign, which a change never moves |
| `gameMaster` | an identifier | the member who changes the series and its sessions |
| `start` | a moment, in its time zone | the first session |
| `rule` | a `RecurrenceRule` of `phil/recurrence` | how the sessions repeat, `COUNT` and `UNTIL` included |
| `players` | 0 to 50 distinct identifiers, the game master not among them | who plays |
| `minutes` | 15 to 1440, 180 by default | how long a session lasts |
| `quorum` | 1 to the number of players, 1 by default | the fewest players not absent for a session to be worth holding |
| `notice` | 0 to 720 hours, 24 by default | how long before a session an absence is announced; later, it is late |
| `reminder` | 1 to 168 hours, or null by default | when the reminder of a session is due, or none |

An identifier is 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting with a letter: an opaque reference, never a
name or an address.

`SessionScheduler::createSeries()` keeps a new series at version 0. `changeSeries()` replaces it with a change made on
the version read, by its game master, who may hand the series to another member; the series stays in its campaign. The
sessions already planned keep their dates and their replies.

## Planning

`plan($series, $through, $by, $limit = 100)` plans the sessions of the rule's occurrences from now through a moment:

- each session's identifier is the series' identifier, `:`, and the occurrence's instant in UTC —
  `iron-coast:20270306T000000Z` — so that planning is idempotent;
- an occurrence that has a session already is left as it is, even when that session was rescheduled or cancelled
  since: planning again plans only what is new, and goes on where the previous call stopped;
- at most `$limit` sessions, 1 to 100, are planned by one call, from at most 1000 occurrences read.

A session planned from the rule keeps the occurrence it stands for in `occurrence`, even once rescheduled.

## Adding a session

`add($series, $startsAt, $by)` adds a session at a start after now. A start that is an occurrence of the rule gives the
session of that occurrence, which planning then skips; any other start gives a session marked `offCalendar`, whose
identifier holds `:x` before its instant. A session that stands for that start already is refused.

## Rescheduling and postponing

`reschedule($session, $startsAt, $by)` moves a planned session that has not started to another start after now. The
replies given for the old start are dropped: the members answer again. The session is marked off calendar when the new
start is not an occurrence of the rule; moving it to the start it has changes nothing.

`postpone($session, $days, $by)` moves a planned session and every later planned session of its series by 1 to 366
days, on the wall clock of each session's time zone: their spacing stays. At most 100 sessions move in one call. A
postponement by whole weeks of a weekly rule keeps the sessions on the calendar.

## Cancelling, holding and substitutes

`cancel($session, $by)` cancels a planned session that has not started. A cancelled session is never planned again
and no longer changes; dates to replace it are proposed as [attendance](attendance.md#proposing-dates) describes.

`hold($session, $by)` marks a planned session held once it has started, by the series' game master or the substitute
who ran it.

`substitute($session, $gameMaster, $by)` gives a planned session a substitute game master, who answers for the session
in place of the series' one; a player of the series is never a substitute. Null, or the series' game master, gives it
back. Paid bookings of a game master are not this library's: an application books them through a booking library and
assigns the substitute here.

## Reading

`series()` and `session()` give one by its identifier. `sessions(SessionQuery $query, $limit = 1000)` gives the
sessions of a series or of every series, starting in a window — both ends included — and in a status, the earliest
first. `Session::isRescheduled()` says whether a session moved from its occurrence, and `endsAt()` when it ends.

Only the series' game master plans, adds, reschedules, postpones, cancels, gives a substitute and proposes dates. A
session that is not planned, or has started, no longer changes.
