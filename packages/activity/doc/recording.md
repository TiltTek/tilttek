# Recording activity

## An activity

An `Activity` is a fact: *this member made this gesture, on this object, at this moment*.

| Part | Type | Rule |
|---|---|---|
| `key` | `IdempotencyKey` | 1 to 255 bytes, no whitespace or control characters |
| `member` | `ResourceReference` | The member, as the application references it: `member:44` |
| `verb` | `Verb` | Lower-case segments of letters, digits and `_`, each starting with a letter, joined by dots; at most 128 bytes |
| `occurredAt` | `DateTimeImmutable` | When the gesture happened, which is not always when it is recorded; between the years 1970 and 9999, in UTC |
| `object` | `?ResourceReference` | What the gesture was about — a game, a comment, an order, another member — or nothing |
| `quantity` | `int` | How much of the gesture: one game, three pages, an order of 42; from 1 to 1 000 000 000 |
| `context` | `Context` | Up to 16 named integers, booleans or strings of at most 100 bytes, which the rules may read |

```php
use Tilttek\Activity\Activity;

$activity = Activity::of(
    key: 'order:2026-0193:paid',
    member: 'member:44',
    verb: 'order.paid',
    occurredAt: $paidAt,
    object: 'order:2026-0193',
    quantity: 42,
    context: ['channel' => 'web', 'first_order' => true],
);
```

`Activity::of()` takes plain values; the constructor takes the value objects.

**An activity carries no text of the member.** A comment is the activity's object, by reference, and the context
holds facts the application sets — a game system, a level, a flag —, never what the member typed. This keeps the
journal out of the way of a moderation or an edit, and keeps its export short.

## The idempotency key

**The same key counts once.** Recording an activity whose key is already in the journal returns the entry already
there, with `Appended::$new` false, and tells no observer. Build the key from what makes the gesture unique, never from
the time it is recorded:

| Gesture | A good key |
|---|---|
| A comment posted | `comment:4512` |
| An order paid | `order:2026-0193:paid` |
| A game played by one of its players | `game:812:player:44` |
| A daily visit | `visit:member:44:2026-11-07` |

The same key for a **different** gesture — another member, verb, object, quantity or context — raises
`IdempotencyConflict`: it is a bug in how the application builds its keys, and recording it would lose one of the two
gestures. The moment is left out of the comparison, since a task run again may stamp it again.

## The moment

**The journal records a gesture dated within its window**: at most thirty days before its clock and five minutes
after it, by default. Outside, `Journal::record()` raises `InvalidActivity` and records nothing. A moment the client
gives, for an action made offline, can then neither reach back to collect the caps of days gone by, nor sit so far
from now that a streak or a period counts its units by the billion.

```php
use DateInterval;
use Tilttek\Activity\Journal;

$journal = new Journal($log, $clock, past: new DateInterval('P7D'), ahead: new DateInterval('PT1M'));
$importer = new Journal($log, $clock, past: null); // a history imported once, with no bound in the past
```

## Cancelling

```php
$journal->cancel('order:2026-0193:paid'); // the order was refunded
```

A cancellation is an entry of its own, at the next position, carrying the cancelled activity: nothing in the journal
is rewritten, and a reader replays it as the reverse of the activity. Cancelling twice returns the first
cancellation; cancelling a key that was never recorded returns null. A cancelled activity cannot be recorded again
under the same key: a new gesture takes a new key.

## Declared verbs

```php
$journal = new Journal($log, $clock, Verbs::of(['news.commented', 'game.*', 'order.paid']));
```

With declared verbs, an activity whose verb no pattern matches raises `UnknownVerb` before anything is written: most
often a typing error that would record a gesture no rule ever reads. Without patterns, every verb is accepted.

## Observers

A `JournalObserver` is told of each **new** entry, recorded or cancelled, right after it is written, in the same
process; a duplicate is not told. An observer that fails lets its exception through, after the entry is written.
`LiveProjections` is the observer that keeps the projections up to date ([Projections](projections.md)).
