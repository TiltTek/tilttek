# Opening boxes

## Opening once

```php
$opened = $advent->open($calendar, $member, 3);
```

`open()` answers an `OpenedBox`: the `Opening` recorded — the calendar, the member, the day and the moment —, the box's
`Surprise`, and `first`, true when this call opened the box.

- A box already opened answers its first opening, with `first` false, whatever the moment: even after the calendar
  closes, a member sees what their box held.
- A box never opened opens when its window contains the clock's moment. Before its day, `open()` throws
  `BoxNotOpenable` with the moment it opens; after its window, `BoxNotOpenable` with the moment it closed.
- A day the calendar has no box for throws `InvalidCalendar` before the store is read.

The guard is the store's `add()`, which keeps one opening per calendar, member and day. When two requests open the same
box at once, one keeps its opening; the other finds it there and answers it, with `first` false.

## Granting the surprise

Each first opening dispatches `Event\BoxOpened`, once, with the calendar's identifier, the member, the day, the
surprise and the moment. The application's listener grants the surprise: it credits the points, awards the badge,
reveals the content or draws a code from the pool.

```php
#[AsEventListener]
public function __invoke(BoxOpened $event): void
{
    match ($event->surprise->kind) {
        SurpriseKind::Points => $this->ledger->credit($event->member, $event->surprise->quantity, $event->idempotencyKey()),
        SurpriseKind::Badge => $this->badges->award($event->member, $event->surprise->reference),
        SurpriseKind::Content => $this->unlocks->unlock($event->member, ResourceReference::fromString($event->surprise->reference)),
        SurpriseKind::Code => $this->codes->handOut($event->member, $event->surprise->reference),
    };
}
```

`idempotencyKey()` — `advent:<calendar>:<member>:<day>` — lets a ledger refuse a second credit when a listener is
retried. The event carries references only, so a listener that hands the work to a message queue sends them as they
are.

## The boxes a member sees

```php
foreach ($advent->boxes($calendar, $member) as $box) {
    $box->day; $box->date; $box->state; $box->window;
    $box->surprise;  // only when opened
    $box->openedAt;  // only when opened
}
```

`boxes()` answers every box by day; `box()` answers one. A `BoxState` is:

| State | When |
|---|---|
| `Locked` | Its day has not come |
| `Openable` | Its window contains the moment; `isOpenable()` is true |
| `Opened` | The member opened it: its surprise and moment are shown |
| `Missed` | Its window ended before the member opened it |

A box not opened never carries its surprise, so a page cannot reveal what the next days hold.

## Celebrating an opening

The opening of a box is a moment worth marking. The library leaves the celebration to the application: a listener of
`BoxOpened`, or the page that receives `first` true, starts the animation the site's interface kit offers — confetti,
fireworks — and respects the member's choice of reduced motion. The library adds no animation and no sound of its own.

## Erasure

`$advent->erase($member)` removes the member's openings in every calendar and answers how many there were. What the
listener granted lives in the application, which erases it with the rest of the member's data.
