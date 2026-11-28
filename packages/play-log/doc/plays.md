# Plays

## A play

A `Play` is a game played once:

| Part | Shape |
|---|---|
| `id` | The identifier the application chooses: 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter. |
| `game` | The identifier of the game in `tilttek/game-catalog`, of the same shape, where "@" is also allowed. |
| `recordedBy` | The member who recorded the play: they alone correct or delete it. |
| `seats` | 1 to 100 `Seat` values, in the order given; a member holds one seat at most. |
| `result` | A `Result`: `Competitive` by default, `CooperativeWin`, `CooperativeLoss` or `Unfinished`. |
| `playedAt` | When the play took place, or null when the date is unknown. |
| `minutes` | How long it lasted, 1 to 1440, or null when unknown. |
| `group` | The identifier of the group it was played in, or null. |
| `place` | Where it took place: one line of 1 to 120 characters, trimmed, without control characters or direction overrides, or null. |
| `version` | 0 for a new play; the store raises it at each write. |

A play whose date is unknown stays undated: it is never given the date it was recorded, and a period leaves it out.
Identifiers are opaque — a member's, a group's or a play's holds no "@", so that an e-mail address is never one — and
the place is the only text a play holds: no comment, no name of a player.

## Seats

A `Seat` is a member, by their identifier, or an anonymous player, with no name at all:

```php
new Seat('member-2', score: 37);           // a member
new Seat(score: 30, winner: true);         // an anonymous player who won
Seat::members(['member-1', 'member-2']);   // the seats of members, without scores
Seat::guests(2);                           // two anonymous seats
```

A score is an integer from -1 000 000 000 to 1 000 000 000, or null when the game counts none. Who won is told by the
seat and the result:

| Result | Who won |
|---|---|
| `Competitive` | The seats marked `winner`: none, one or several, whatever their scores — a game where the lowest score wins is told the same way. |
| `CooperativeWin` | Every seat. |
| `CooperativeLoss` | No seat. |
| `Unfinished` | No seat. |

Only the seats of a competitive play are marked as winners. `Play::won()` tells whether a seat won and
`Play::winners()` gives the seats that won.

## Recording and correcting

`PlayLog::record()` keeps a new play at version 0, whose identifier no kept play has, on a game of the catalogue that
is not hidden. `PlayLog::correct()` replaces a play with its correction, made by the member who recorded it on the
version they read: the member who recorded it stays the same, and a game changed for another is checked as on
recording. A game hidden in the catalogue after a play was recorded stays on that play. `PlayLog::delete()` deletes a
play, by the member who recorded it.

## Linking the seats to members

A member's seat counts for them only once it is confirmed. The log sets the confirmation, never the caller:

- the seat of the member who records the play is confirmed at once;
- the seat of another member is confirmed when the application's `Contract\PlayerConsent` says that member agreed
  beforehand to be linked to the plays of the member who records it, and waits for them otherwise;
- a correction keeps the seats confirmed before, and treats a member it adds as on recording.

`PlayLog::confirm()` confirms the member's own seat; confirming it again changes nothing. `PlayLog::decline()` makes
it anonymous, whether it was confirmed or not: its score and its win stay, so that the play still reads the same, and
it no longer counts for the member.

`Play::seenBy()` gives the play as a reader sees it: the member who recorded it reads every seat as entered; anyone
else, and null for a reader who is not signed in, reads the seats that are not confirmed as anonymous, but their own.
Which plays a member may open — their own, those of a group they belong to — is the application's decision.

## Reading

`PlayLog::play()` gives a play as it is kept. `PlayLog::plays()` gives the plays a `PlayQuery` matches, 1 to 1000 at
once, the latest first, those whose date is unknown last, then by identifier:

| Query | The plays |
|---|---|
| `PlayQuery::ofMember($member)` | Where the member holds a confirmed seat. |
| `PlayQuery::pendingFor($member)` | Where the member holds a seat they have not confirmed: the plays to ask them about. |
| `PlayQuery::recordedBy($member)` | That the member recorded. |
| `PlayQuery::ofGroup($group)` | Of the group. |

`forGame()` narrows a query to one game, and `between()` to a period, from its start, included, to its end, excluded;
either may be open. A period leaves out the plays whose date is unknown.

## Forgetting a member

`PlayLog::forget()` forgets a member, up to a number of plays at once, from 1 to 1000: it deletes the plays they
recorded, then makes their seats of the others' plays anonymous, confirmed or not. It returns how many plays it
deleted or changed; an application calls it again until it returns 0, from a task when a member has many plays. The
plays another member recorded keep their scores and results, so that their statistics stay true.
