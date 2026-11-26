# Installation

## Requirements

- PHP 8.5 or later.
- `phil/checkin`, whose ledger keeps the check-ins; with `phil/checkin-bundle`, whose endpoints dispatch
  `CheckInRecorded`, for a desk.
- `tilttek/activity`, whose journal records the attendance.

## Installing

```sh
composer require tilttek/check-in-rewards
```

## Wiring the recorder

The library registers no service and depends on no bundle: the application registers `AttendanceRecorder` with three
services it already has — the check-in ledger, its resolver of presences, and the journal —, and calls it from its
listener of `Phil\CheckinBundle\Application\Event\CheckInRecorded` with the scan's identifier.

```yaml
# config/services.yaml
services:
    App\Attendance\TicketPresences: ~

    Tilttek\CheckInRewards\AttendanceRecorder:
        arguments:
            $ledger: '@Phil\Checkin\Contract\CheckInLedger'
            $resolver: '@App\Attendance\TicketPresences'
            $journal: '@Tilttek\Activity\Journal'

    Tilttek\CheckInRewards\AttendanceReader:
        arguments: ['@Tilttek\Activity\Journal']
```

```php
#[AsEventListener]
final readonly class RewardAttendance
{
    public function __construct(
        private AttendanceRecorder $recorder,
    ) {
    }

    public function __invoke(CheckInRecorded $scan): void
    {
        $this->recorder->record($scan->id);
    }
}
```

The recorder reads the outcome, the conflict and the moment from the ledger, never from the event: whatever calls it
hands it an identifier, nothing it could make up.

The recorder and the reader keep nothing between two calls: one instance serves every request, in a worker as in a
request.

Called from the listener, the recorder records in the same process as the scan. An application that rewards attendance
later — the desk answers first — sends a Messenger message that carries the scan's identifier, and its handler calls
the recorder with it; the scan's identifier keeps the attendance counted once however many times the message is
handled.

## The journal

The journal records an activity dated within its window, thirty days before its clock by default. A scan made offline
and synchronised later than that is refused by the journal: an application whose desks stay offline longer gives the
journal a wider window.

A journal that declares its verbs declares `attendance.*` with them, `AttendanceVerbs::PATTERN`.
