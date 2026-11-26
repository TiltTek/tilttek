# tilttek/session-scheduler

**Keep a campaign's sessions on track: recurring dates planned in batches, absences with notice, postponing, substitute
game masters and reminders.**

tilttek/session-scheduler keeps the sessions of a role-playing campaign. A **series** holds:

- **when** — the first session, in its time zone, and an iCalendar (RFC 5545) rule read by `phil/recurrence`: every
  Friday, every other Sunday, the first Saturday of the month. Sessions keep the first one's time of day on the wall
  clock of its time zone, across changes of time;
- **who** — the game master, who changes the series and its sessions, and up to 50 players;
- **the table's settings** — how long a session lasts, the quorum of players below which a session is not worth
  holding, the notice an absence is announced with (24 hours by default), and one reminder before each session, or
  none.

The game master plans the sessions of the rule in batches, up to 100 at a time; planning again plans only what is new.
A session can be added at any start: one that is not an occurrence of the rule is marked off calendar, never refused.
A session is rescheduled, postponed with every later session by the same number of days so that their spacing stays,
cancelled, given a substitute game master, or marked held once it has started.

Each player says they are coming or absent. An absence later than the notice is recorded all the same, marked late, for
the game master to decide; when absences leave fewer players than the quorum, the game master is told, and the session
is never cancelled on its own. An application may cap the upcoming sessions a member says they are coming to.

Dates are put to the members through a port: a first date, or free days at the series' time of day to replace a session
that is skipped. A scheduling poll answers through an adapter the application writes, described in the
[extension points](doc/extension-points.md). The reminders due in a window are listed for the application's scheduled
task to send.

The facts of a series — created, changed, a session planned, rescheduled, cancelled or held, a member coming or absent,
the game master absent, a substitute assigned, the quorum lost or restored, dates proposed — go to an observer, each
once, by identifiers only.

It is a plain PHP library: one service, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Series`, `Session`, `Reply`, `SessionStatus`: a series and its sessions, bounded and checked | Implemented and tested |
| `SessionScheduler`: series, batch planning, sessions added off calendar, reschedule, postpone, cancel, hold, substitutes | Implemented and tested |
| Attendance: coming, absent with notice or late, the quorum, a cap of upcoming sessions per member | Implemented and tested |
| `DateProposal` and `Contract\DateProposer`: dates for a first session or a replacement | Implemented and tested |
| `Reminder`: the reminders due in a window | Implemented and tested |
| `Store\InMemorySessionStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the scheduled reminders, the forms and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Series and sessions](doc/sessions.md) | Series, planning, adding, rescheduling, postponing, cancelling, holding and substitutes. |
| [Attendance and reminders](doc/attendance.md) | Coming and absent, the notice, the quorum, the cap, proposals and reminders. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the proposer and the adapter of a scheduling poll. |
| [Exceptions](doc/exceptions.md) | `SchedulerException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Phil\Recurrence\RecurrenceRule;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\SessionScheduler;

$scheduler = new SessionScheduler($store, $clock, $observer, $proposer);   // your SessionStore, a PSR-20 clock, ports

$scheduler->createSeries(new Series(
    'iron-coast',
    'campaign-7',
    'member-1',                                                   // the game master
    new DateTimeImmutable('2027-03-05 19:00', new DateTimeZone('America/Toronto')),
    RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=FR'),
    ['member-2', 'member-3', 'member-4'],
    minutes: 240,
    quorum: 2,
    reminder: 6,                                                  // six hours before each session
));

$sessions = $scheduler->plan('iron-coast', new DateTimeImmutable('2027-06-30'), 'member-1');

$scheduler->absent($sessions[0]->id, 'member-3');                 // late after the 24 hours of notice, still recorded
$scheduler->postpone($sessions[1]->id, 7, 'member-1');            // this session and every later one, a week later
$scheduler->proposeReplacement($sessions[2]->id, new DateTimeImmutable('2027-04-15'), 'member-1');

foreach ($scheduler->reminders($previousRun, $now) as $reminder) {
    // send a message to $reminder->members for $reminder->session
}
```

## Requirements

PHP 8.5 or later, with `phil/recurrence` and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
