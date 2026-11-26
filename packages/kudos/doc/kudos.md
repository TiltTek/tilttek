# Giving and reading thanks

## Giving

```php
$given = $kudos->give($giver, $receiver, 'well_played', ResourceReference::of('game', '812'));
$given->new;            // false when the same thanks were already given under the same key
$given->thanks->key;    // the idempotency key
```

`give()` takes the giver and the receiver as `ResourceReference`, a message — its name or a `Message` — and an
optional object, the thing the thanks are for. The message is looked up in the application's list of messages: a name
outside it throws `UnknownMessage`. No text of the member is kept.

The thanks are recorded as an activity of the verb `kudos.given`, dated by the clock:

| Activity | Holds |
|---|---|
| member | the giver |
| object | the receiver |
| context `message` | the message's name |
| context `object` | the reference of the object, at most 100 bytes, or nothing |

An object whose reference is longer than 100 bytes throws `InvalidKudos`.

## Keys

The thanks are recorded once per idempotency key. Given again under the same key, `give()` returns the thanks already
given, with `new` false: it refuses nothing, even when a cap has been reached since, and tells the observers nothing.
The same key given to other thanks throws the `IdempotencyConflict` of `tilttek/activity`.

The application may pass its own key — the identifier of the request, say. Without one, the key is
`kudos:` followed by a SHA-256 of the giver, the receiver, the message, the object and the giver's day: the same
thanks clicked twice in a day are given once, and given again the next day.

## Refusals

Thanks are refused with `KudosRefused`, whose `refusal` says why, checked in this order:

| `Refusal` | When |
|---|---|
| `Self` | The giver and the receiver are the same member. |
| `Declined` | The receiver's preferences decline thanks from this giver. |
| `DailyCap` | The giver has given `Limits::$daily` thanks today — five by default. |
| `PairLimit` | The giver has thanked this receiver `Limits::$perReceiver` times in `Limits::$period` — three in seven days by default. |

`refusal($giver, $receiver)` gives the reason without giving anything, or null, and `remainingToday($giver)` the thanks
the giver may still give today: a screen reads them to offer the button or say why not.

The day starts at midnight in the giver's time zone, given by `Contract\MemberTimeZones`, read from the clock. The
period of the limit per receiver ends now and starts `period` earlier, included. Withdrawn thanks still count towards
both, so that giving and withdrawing cannot be repeated without end.

Two requests of the same giver at the same instant may both pass the checks: an application that must hold the cap
under that race takes a lock per giver around `give()`, with `symfony/lock` for example.

## Reading

| Method | Gives |
|---|---|
| `received($member, $after, $limit)` | The thanks the member received |
| `given($member, $after, $limit)` | The thanks the member gave |
| `givenCount($member, $since, $until)` | How many thanks the member gave in the period |

`received()` and `given()` return a `ThanksPage`: up to `$limit` entries of the journal read after the position
`$after` — 50 by default, 1 to 100 —, oldest first, withdrawn thanks left out, and the position to read the next page
after, null when the journal holds no more. A page may hold fewer thanks than its limit, since withdrawn thanks and
their cancellations take their places in the read.

What a member received is theirs to read: the application calls `received()` with the signed-in member only. The
library offers no total of thanks received, no ranking, and no other reading of what a member received. Thanks show
no count on a profile, and a receiver who wants to share them does so through the application.

`givenCount()` counts the thanks given, withdrawn ones left out, from `$since`, included, to `$until`, excluded: the
measure of a badge for generous members ([Extension points](extension-points.md#the-badge-criterion)).

## Withdrawing and cancelling

`withdraw($giver, $key)` withdraws thanks the giver gave; `cancel($key)` removes thanks whoever gave them, for a
moderator. Each returns false when the key records no thanks — or, for `withdraw()`, thanks of another giver — and
true once the thanks are cancelled, the first time and every time after.

A cancellation is an entry of its own in the journal, as `tilttek/activity` writes it: the thanks are never rewritten.
The observers are told of the first cancellation only.

## Erasing a member

A member is erased through the journal, with `Journal::erase()` of `tilttek/activity`: the thanks they gave are
deleted, and the thanks they received are kept for their givers with the receiver forgotten — `Thanks::$receiver` is
null. An object thanked for that is itself a member's reference stays in the context; the application erases what it
derived from the thanks, such as the notifications it sent.

## Events

`Contract\KudosObserver` is told once of `KudosGiven` and `KudosCancelled`, after the journal wrote the entry. Each
event carries identifiers only — the key, the giver, the receiver, the message's name, the object —, for the
application to relay as a notification or a message on its bus. The library sends no notification itself.
