# Storing

`Contract\RatingStore` keeps one `MemberRating` per member and game:

| Method | Does |
|---|---|
| `find($game, $members)` | The ratings of these members for the game, by member; a member without one is left out |
| `hasRecorded($game, $result)` | Whether the game counts the result already |
| `save($ratings, $result, $events)` | Keeps the ratings at once, each at its next version, and counts the result for their game with its events to deliver; `ResultAlreadyRecorded` when it is counted already, `RatingConflict` when a rating changed since it was read |
| `undelivered($game, $result)` | The events of the result not delivered yet, in order |
| `markDelivered($game, $result, ...$keys)` | Forgets the events of these keys, dispatched without an exception |
| `standings($game, $limit, $minGames)` | The public ratings with at least `$minGames` rated games, and one at least, the highest score first, then by member |
| `ofGame($game)` | Every rating of the game |
| `replace($game, $ratings, $results)` | Replaces every rating of the game, and the results it counts, at once: what a replay computed; the events of the game not delivered yet are cancelled |
| `ofMember($member)` | Every rating of the member |
| `forget($member)` | Erases every rating of the member, and the events about them not delivered yet |

A rating read at version 0 is new: the store keeps it only when it holds none for that member and game. Any other is
kept only when the store holds it at the version it was read at. Two results of the same members recorded at once
cannot both land: the second throws `RatingConflict`, nothing of it is kept, and the application records it again. On
a database, the row of a rating carries its version, updated with `WHERE version = ?` in one transaction for all the
members of the result, with a unique key on the game and the member.

The results a game counts are kept beside the ratings: on a database, a table of the game and the result's identifier
with a unique key on both, written in the same transaction as the ratings, so that a second recording of the result
fails on the key and keeps nothing; `replace()` empties the game's rows and its events not delivered, and writes those of the replay. The
identifiers are opaque references to the results, never a member.

The events of a result not delivered yet are kept in the same transaction: on a database, a table of the game, the
result, the event's key, the member and its kind — `changed` or `peak` —, with a unique key on the game, the result and
the key; `markDelivered()` deletes its rows, and `forget()` those of the member.

`MemberRating::toArray()` gives the plain values to keep — the rating itself as whole numbers by name — and
`MemberRating::fromArray($data, $system)` reads them back with the system that computed them, refusing a rating of
another system. The standings read the `score` column, in hundredths, and the `visibility` column; index them by game.

`Store\InMemoryRatingStore` keeps everything in the process, for tests and scripts.
