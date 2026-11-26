# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the series and sessions | Implement `Contract\SessionStore`, and pass it to `SessionScheduler`. |
| Act on what happened | Implement `Contract\SessionObserver`, and pass it to `SessionScheduler`. |
| Put dates to the members | Implement `Contract\DateProposer`, and pass it to `SessionScheduler`. |
| Choose dates by a scheduling poll | Write a `DateProposer` that opens the poll, and call back the scheduler when it closes. |
| Give the time | Pass a PSR-20 `ClockInterface`. |

## The store

`Contract\SessionStore` keeps the series and sessions, each at a version:

- `series()` and `session()` give one by its identifier, or null;
- `saveSeries()` and `save()` keep one at the next version and return it. One at version 0 is new, and the store has
  none of its identifier; any other is kept only when the store holds it at that version, and `ScheduleConflict` is
  raised otherwise, nothing kept;
- `sessions()` gives the sessions a `SessionQuery` matches, at most the limit, the earliest first, then by identifier.
  `SessionQuery::matches()` states what each query means; a store reads it from `series`, `from`, `through` and
  `status`, by an index on the series with the start, and one on the start;
- `countComing()` counts the planned sessions starting after a moment that a member said they are coming to, in every
  series: a store keeps the replies where it can count them by member.

`Store\InMemorySessionStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database. A series holds strings, integers, a moment with its time zone and its rule, which
`(string) $rule` writes as RFC 5545 does and `RecurrenceRule::parse()` reads back; a session holds strings, integers,
a boolean, two moments, an enumeration and its replies by member.

## The observer and its events

`Contract\SessionObserver::notify()` is told each fact once the series or session is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\SeriesCreated` | A series was created. | |
| `Event\SeriesChanged` | The game master changed a series. | |
| `Event\SessionPlanned` | A session was planned from the rule, or added. | whether it is off calendar |
| `Event\SessionRescheduled` | A planned session moved, alone or by a postponement. | whether it is off calendar |
| `Event\SessionCancelled` | A planned session was cancelled. | |
| `Event\SessionHeld` | A session took place. | |
| `Event\AttendanceConfirmed` | A member said they are coming. | the member |
| `Event\AbsenceAnnounced` | A player said they are absent. | the member, whether it is late |
| `Event\GameMasterAbsent` | The game master who runs the session said they are absent. | the game master, whether it is late |
| `Event\SubstituteAssigned` | A substitute runs the session, or the series' game master again. | the substitute, or null |
| `Event\QuorumLost` | An absence left fewer players than the quorum. | |
| `Event\QuorumRestored` | A player coming brought the session back to the quorum. | |
| `Event\DatesProposed` | Dates were put to the members. | the session replaced, or null |

Every event implements `Event\ScheduleEvent`, whose `series` is an identifier; the events of one session implement
`Event\SessionEvent`, which adds `session`. A reply comes before the quorum it changes. An application sends a message
from them — through Messenger, with the identifiers alone — and reads the rest from the store: a notice to the game
master on `QuorumLost` or `GameMasterAbsent`, a survey after `SessionHeld`, the campaign's statistics from the sessions
held.

## The proposer and a scheduling poll

`Contract\DateProposer::propose()` receives a `DateProposal`: the series, the session replaced or null, the candidate
starts, the earliest first, the members asked — the game master who runs the session, then the players — and the
minutes. It puts them to the members as the application chooses: a scheduling poll, a grid of availabilities, a
message.

A scheduling poll, such as the one of `phil/scheduling-poll-bundle`, answers later. The adapter opens the poll with the
proposal's dates as its options and remembers what it is for; when the poll closes, the application calls the scheduler
with the date chosen — `add()` for a new session, `reschedule()` for a replacement. With a poll service of the
application's own that opens a poll on fixed options and calls back when it closes:

```php
use Override;
use Tilttek\SessionScheduler\Contract\DateProposer;
use Tilttek\SessionScheduler\DateProposal;

final readonly class PollDateProposer implements DateProposer
{
    public function __construct(private SchedulingPolls $polls)   // the application's poll service
    {
    }

    #[Override]
    public function propose(DateProposal $proposal): void
    {
        $this->polls->open(
            subject: $proposal->session ?? $proposal->series,  // what the poll decides, read back when it closes
            options: $proposal->candidates,                       // instants: each member answers in their own zone
            voters: $proposal->members,
            minutes: $proposal->minutes,
        );
    }
}

// When the poll closes, with the option most members answered yes or if need be:
$closed->subject === $series
    ? $scheduler->add($series, $chosen, $gameMaster)
    : $scheduler->reschedule($closed->subject, $chosen, $gameMaster);
```

The package depends on no poll library: the adapter belongs to the application.

## The clock

`SessionScheduler` reads the time from the PSR-20 `ClockInterface` it is given, and from nothing else: what is past,
whether an absence is late, where planning starts. A test gives it a clock it sets.
