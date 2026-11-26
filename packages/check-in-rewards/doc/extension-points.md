# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\PresenceResolver` | What a check-in means: the member, the session, the event, the place, the track, the timezone | [The resolver](#the-resolver) |
| `AttendanceCriterion` | The attendance rules of its badges and quests | [Criteria](#criteria) |
| `AttendanceRecorder` | The listener of `CheckInRecorded`, or the message handler, that calls it with the scan's identifier | [Installation](installation.md#wiring-the-recorder) |
| `AttendanceVerbs::PATTERN` | The verbs its journal allows, when it declares them | [Verbs](#verbs) |

## The resolver

The ledger keeps identifiers only: the scan, the credential, the checkpoint and the device. The application knows the
rest, and says it with a `Contract\PresenceResolver`, which reads the check-in as the ledger keeps it:

```php
final readonly class TicketPresences implements PresenceResolver
{
    public function resolve(CheckIn $checkIn): ?Presence
    {
        $ticket = $this->tickets->find($checkIn->credentialId);
        $session = $this->programme->atCheckpoint($checkIn->checkpoint->value);

        if (null === $ticket?->member || null === $session) {
            return null;
        }

        return new Presence(
            ResourceReference::of('member', $ticket->member),
            ResourceReference::of('session', $session->id),
            ResourceReference::of('event', $session->event),
            place: $session->room,
            track: $session->track,
            timezone: new DateTimeZone($session->timezone),
        );
    }
}
```

It returns null for an admission that rewards no one: a credential of no member, a member who left or was erased, a
checkpoint outside any event. The event, written as its reference, the place and the track are written in the context
of the activities, which holds strings of 1 to 100 bytes; `Presence` refuses longer ones with `InvalidPresence`. A
presence without an event — a kiosk of a store — records the session, the day and the week of the member, with no
event in their context.

The days and the weeks are read in the presence's timezone: a scan at 23:30 in Montréal counts on that day, whatever the
day in UTC. A week is an ISO week, `2027-W09`.

## What the recorder records

For one admission, under the keys below, in the journal:

| Verb | Once per | Object | Context |
|---|---|---|---|
| `attendance.recorded` | Scan | The session | `event`, `place`, `track`, `day` |
| `attendance.session` | Member, event and session | The event | `event` |
| `attendance.day` | Member, event and day | The event | `event`, `day` |
| `attendance.place` | Member, event and place, when the presence has a place | The event | `event`, `place` |
| `attendance.week` | Member and week | — | `week` |

The key of an admission is `check-in:` and the scan's identifier. The key of a first time is its verb and a SHA-256
digest of the member and of what makes it unique, each part prefixed by its length. A scan handed again, a listener
run twice, a message handled twice record nothing more; a scan resolved later to another presence than the first time
is an `IdempotencyConflict` of the journal.

A moment is the moment of the scan, but never later than the moment the server received it: a device whose clock runs
ahead cannot move a visit to the next day.

## Criteria

The library creates no badge and opens no quest: the application declares its rules with `AttendanceCriterion`, and
puts them in the badges and quests it defines.

| Criterion | Counts | For |
|---|---|---|
| `sessions(int $sessions, ?ResourceReference $event = null)` | Distinct sessions, tables or stands, of the event when given | A convention passport; N games played |
| `everyDay(ResourceReference $event, int $days)` | Days of the event attended; the number of days of the event for every day | Present every day |
| `distinctPlaces(int $places, ?ResourceReference $event = null)` | Distinct places, of the event when given | An explorer of tables or stands |
| `place(string $place, ?ResourceReference $event = null)` | One admission at the place | A table discovered |
| `weeks(int $weeks)` | Weeks with at least one visit, in a row or not | A calendar of visits without penalty |

`toBadgeCriterion()` writes the criterion as the `CriterionReader` of `tilttek/badges` reads it, a `count` with its
verbs, context and times; `toQuestStep(string $code)` writes it as a step the `QuestReader` of `tilttek/quests` reads:

```php
$badge = new CriterionReader()->read(AttendanceCriterion::everyDay($convention, 3)->toBadgeCriterion());

$quest = new QuestReader()->read('explorer', ['steps' => [
    AttendanceCriterion::distinctPlaces(5, $convention)->toQuestStep('tables'),
    AttendanceCriterion::place('main-stage', $convention)->toQuestStep('stage'),
]]);
```

A count is 1 to 1,000,000,000; `InvalidCriterion` refuses another, and an event or a place longer than 100 bytes.

A series of weeks counts weeks attended, never weeks in a row: a member who misses a week keeps what they had and goes
on from there. A streak of days in a row, which a missed day resets, is a `streak` criterion of `tilttek/badges` on
`attendance.day`, for an application that wants one.

## Privacy

`AttendanceReader::summary($viewer, $member, $event)` gives a member their own distinct sessions, days, places and
weeks; any other viewer gets `AccessDenied`. The library offers no ranking of attendance, no list of the members
present and no title held by the most frequent visitor of a place, which would show a person's routine. An application
that publishes something about attendance publishes it from what each member chose to show, such as the badges they
display.

Erasing a member is the journal's: `Journal::erase()` deletes the activities of the member, attendance included. The
library keeps nothing of its own.

## Verbs

The verbs of the library are the constants of `AttendanceVerbs`. A journal built with declared verbs refuses a verb it
does not know with `UnknownVerb`; such a journal declares `AttendanceVerbs::PATTERN`, `attendance.*`.
