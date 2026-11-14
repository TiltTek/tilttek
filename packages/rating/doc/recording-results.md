# Recording results

## A result

`GameResult` is one game played: a stable identifier, the game, when it was played, its sides and the rank of each
side. A side is one player or a team; rank 1 is first, and sides with the same rank drew.

```php
GameResult::duel('play-81', 'chess', $at, 'member-1042', 'member-1077');              // the first won
GameResult::duel('play-82', 'chess', $at, 'member-1042', 'member-1077', draw: true);  // a draw
GameResult::placings('play-83', 'catan', $at, [['member-7'], ['member-3', 'member-9'], ['member-5']]);
new GameResult('play-84', 'pandemic-legacy', $at, [['m-1', 'm-2'], ['m-3', 'm-4']], [2, 1]);   // team 2 won
```

`placings()` lists the members from the first place, each placing holding those who finished there together. A result
has 2 to 64 sides of 1 to 32 members, 256 members at most, each on one side only. The identifiers of the result, the
game and the members are 1 to 128 letters, digits, `_`, `.`, `:`, `@` or `-`, starting with a letter or a digit: an
opaque reference, never a name.

`toArray()` gives the result as plain values and `fromArray()` reads them back, the time as ISO 8601 with its offset —
what a source of results keeps.

## Recording

```php
$changes = $ratings->record($result);
foreach ($changes as $change) {
    $change->member; $change->before; $change->after; $change->delta(); $change->peak;
}
```

`record()` refuses a result its game counts already, reads the rating of every member — the initial one for a member's first rated game of that game —, rates
the result with the system, and keeps the new ratings of all the members at once. A change is in hundredths: the Elo
rating, or the conservative estimate of a skill. `peak` is true when the member went above the highest score they
reached, once they played the rated games `celebrateAfter` asks for.

A result is recorded once. The store counts the identifier of each result it rated, game by game, in the same write as
the ratings: recording the same result again — a double click, a message delivered twice — throws
`Exception\ResultAlreadyRecorded`, keeps nothing and dispatches nothing, once the events of the result are all
delivered; while some are left, it dispatches them instead, rates nothing again and returns no change. Two recordings
of the same result at once never both count: the first write counts the result, and the second is refused, by the
result or by the versions of the ratings. A replay counts the results it rated again, from its source; a friendly
result is never counted, so recording it twice changes nothing twice.

## Friendly results

A result built with `friendly: true` is played outside the ratings: `record()` changes nothing, keeps nothing and
dispatches nothing. A replay reads it in its place in the order, and skips it.

## The events

Once the ratings are kept, `record()` dispatches, member by member in the order of the result, `Event\RatingChanged`,
then `Event\PeakReached` for a member whose change is a new highest score. See [Celebrations](celebrations.md).

The events are kept with the result, in the same write as the ratings, and each one stays in the store until it was
dispatched without an exception. A listener that throws leaves its event there; the other events are dispatched all
the same, and the first exception is thrown again once every event was tried. The ratings are kept: the application
makes up the failure by recording the same result again, which dispatches the events left without rating anything
again, or by `deliver($game, $result)`, which returns how many went.

```php
try {
    $ratings->record($result);
} catch (ListenerFailed $failure) {   // whatever the listener threw
    $ratings->deliver($result->game, $result->id);
}
```

An event may thus be dispatched more than once. The listeners are idempotent: each event carries a `key()`, the same
each time it is dispatched again — `chess/r1/ann/changed`, `chess/r1/ann/peak` — and a listener that awards points or
sends a message does it once per key. Without a dispatcher, nothing is kept to deliver.
