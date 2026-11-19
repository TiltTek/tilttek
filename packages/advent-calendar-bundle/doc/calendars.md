# Calendars

A calendar has a box for each day from its first date, one surprise per box, in its timezone. Its days start at
midnight on the wall clock of the timezone, across changes of time. No box opens before its day.

## Opening policies

Each calendar chooses how long a box stays openable once its day has come:

| Policy | A box stays openable |
|---|---|
| `until_closing`, the default | From its day until the calendar closes: a member who missed a day opens its box later. |
| `same_day` | On its day only; after it, the box is missed. |
| `catch_up` | On its day and the 1 to 31 days after it, never past the closing. |

The closing is the date of the last box unless the calendar names a later one, up to 366 days after it. The screen
says the policy in a sentence, so that a member knows whether a missed day is lost.

## Calendars of the configuration

The configuration declares calendars by identifier, as [Configuration](configuration.md) shows. They are built once,
when the container is, and each is checked by the library: a calendar that does not hold together stops the
application at its first request rather than on the day of a box. A calendar with `published: false` is not shown.

## Calendars of the database

With the ORM, the bundle maps `CalendarRecord` to the table `tilttek_advent_calendar`: a site edits its calendars as it
edits its other content. The surprises are kept in order as JSON, since a box is read with its calendar, never alone.

`CalendarType` is the form of a calendar, on a `CalendarRecord`: its identifier, title, timezone, first and closing
dates, policy, days of catch-up, publication, and a collection of `SurpriseType`, one per box. When it is submitted,
the library checks the calendar as a whole and its refusal becomes an error of the form. The option `existing: true`
fixes the identifier of a calendar that exists, since its openings are kept under it.

```php
$record = $calendarId === null ? new CalendarRecord() : $entityManager->find(CalendarRecord::class, $calendarId);
$form = $this->createForm(CalendarType::class, $record, ['existing' => $calendarId !== null]);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $entityManager->persist($record);
    $entityManager->flush();
}
```

A calendar of the database that is not published is not shown. One whose fields no longer hold together — edited by
hand, say — is not shown either, and is logged as an error; `tilttek:advent-calendar:check` names it with its reason.

## Surprises

A surprise is a reference to a reward the application grants:

| Kind | Reference | Quantity |
|---|---|---|
| `points` | The ledger, `points` by default | How many points |
| `badge` | The badge | 1 |
| `content` | The content revealed, `recipe:gingerbread` | 1 |
| `code` | The pool a code is drawn from | How many codes |

An unopened box never reveals its surprise: the screen shows it once the member has opened the box, and the command
`tilttek:advent-calendar:show` shows it to the people who run the site.

## Granting the surprises

The library dispatches `BoxOpened` once, when a member first opens a box, through the application's event dispatcher.
Opening the box again answers the same opening and dispatches nothing. The event carries references only, and a key the
application's ledger may refuse a second time:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\SurpriseKind;

#[AsEventListener]
final readonly class GrantSurprise
{
    public function __invoke(BoxOpened $event): void
    {
        match ($event->surprise->kind) {
            SurpriseKind::Points => $this->points->credit($event->member, $event->surprise->reference, $event->surprise->quantity, $event->idempotencyKey()),
            SurpriseKind::Badge => $this->badges->award($event->member, $event->surprise->reference),
            default => null,
        };
    }
}
```

A listener that grants through a slow service dispatches a Messenger message that carries the calendar, the member's
reference and the day, and grants it from the handler.
