# tilttek/check-in-rewards

**Reward attendance from the door, never from a phone: check-in scans become passports, every-day, places-discovered
and weekly visit criteria.**

tilttek/check-in-rewards turns the admissions of `phil/checkin` into attendance in the journal of `tilttek/activity`,
where the criteria of `tilttek/badges` and `tilttek/quests` read it. The scan at the door, as the
check-in ledger keeps it, is the only source it trusts: no position a phone declares, no form a member fills in.

`AttendanceRecorder` takes a scan's identifier — from a listener of the `CheckInRecorded` event of
`phil/checkin-bundle`, or from a message — and reads the check-in from the ledger. For an admission — outcome admitted,
no conflict between the device and the server — it asks the application what the check-in means: the member, the
session, table or stand they came in to, the event, the place and the track, and the timezone of the event. It records
the admission once under the scan's identifier, and with it the first admission of the member to the session, on the
day, at the place and in the week, each once, under a key drawn from what makes it unique. A count of activities is then
a count of distinct sessions, days, places and weeks.

`AttendanceCriterion` writes the rules an application declares — a convention passport of N sessions, every day of an
event, N places discovered, one place discovered, N weeks of visits — as `tilttek/badges` and `tilttek/quests` read
their criteria. A series of weeks counts the weeks a member came, not weeks in a row: a week missed takes nothing away.

Attendance stays the member's. `AttendanceReader` reads a member's attendance for that member only; there is no ranking
of attendance, no list of who came and no title that shows someone's habits. Erasing a member through
`Journal::erase()` of `tilttek/activity` erases their attendance with the rest of their activity.

It is for a game store with a kiosk at the door, a convention with check-in desks at its rooms and tables, a club that
counts its evenings. It is a plain PHP library whose dependencies are `phil/checkin`, for the check-ins and their
ledger, `tilttek/activity`, for the journal, and `phil/resource`, for the references; it works with the desk of
`phil/checkin-bundle` without depending on it.

## Status

| Part | State |
|---|---|
| `AttendanceRecorder`, `Contract\PresenceResolver`, `Presence`: admissions recorded once, with their first session, day, place and week | Implemented and tested |
| `AttendanceCriterion`: sessions, every day, distinct places, one place, weeks, for badges and quests | Implemented and tested |
| `AttendanceReader`, `AttendanceSummary`: a member's attendance, for that member only | Implemented and tested |
| Services registered by a bundle | Not started: the application registers the recorder and its listener |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and calling the recorder from the check-in event. |
| [Extension points](doc/extension-points.md) | The resolver, the criteria, and the verbs the journal must allow. |
| [Testing](doc/testing.md) | The library's tests, and testing code that rewards attendance. |
| [Reference](doc/reference.md) | Every public type, with its role. |
| [Exceptions](doc/exceptions.md) | What each exception means, and the exceptions of the journal that reach the caller. |

## Quick start

```php
use Tilttek\CheckInRewards\AttendanceCriterion;
use Tilttek\CheckInRewards\AttendanceRecorder;

// The recorder, on the check-in ledger and the journal of tilttek/activity, called for each scan the desk recorded.
$recorder = new AttendanceRecorder($ledger, $presences, $journal);
$dispatcher->addListener(CheckInRecorded::class, static fn (CheckInRecorded $scan) => $recorder->record($scan->id));

// A badge after five sessions of the convention, as tilttek/badges reads it.
$criterion = new CriterionReader()->read(AttendanceCriterion::sessions(5, $convention)->toBadgeCriterion());
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
