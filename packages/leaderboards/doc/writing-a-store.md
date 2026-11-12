# Writing a store

An application keeps the boards in its own storage by implementing four contracts — or keeps the scores in Valkey with
`ValkeyScoreStore`, below. Each contract comes with an abstract test case in `tests/Unit/Store/` that any
implementation passes: extend it and return your store from `store()`.

## `ScoreStore`

- `add()` keeps a contribution and returns false when the activity already brought one to that board — a unique key on
  board and activity, written by an insertion that writes nothing when the row is there.
- `remove()` removes and returns every contribution of an activity; `forget()` those of the activities from a moment on
  — all of them without one —; `expire()` those of a board — every key of a board ranked by object — that occurred
  before a moment; `contributions()` those of one member's score, in the order they occurred. A contribution keeps its
  tie-breaks, 0 to 2 integers.
- `save()` replaces a member's score on a board and period, with its tie-breaks; `delete()` removes it; `score()` reads
  it.
- `top()` reads the first scores of a board in a period in the order of the `Ranking` given: the highest value first,
  each tie-break in its direction, then the earliest moment, then the member's reference — among a circle's members
  when one is given. An index on board, period, value, tie-breaks and moment serves it. `Ranking::key()` gives a key
  that sorts byte by byte in that order, for a storage that sorts strings.
- `ahead()` counts the scores that rank before one, as `Ranking::ranksBefore()` says: the better ones, and — when the
  first to reach a score ranks first — the equal ones reached before it or at the same moment by a member written
  first. `participants()` counts the scores of a board in a period.
- `of()` and `eraseMember()` read and remove a member's scores for an export and an erasure; `eraseMember()` removes
  their contributions too.

## `SnapshotStore`

`save()` keeps a snapshot once per board and period and returns false when one is there; `find()` reads it with its
lines in rank order; `placings()` reads a member's lines; `eraseMember()` removes them.

## `AppearanceStore`

`choose()` keeps a member's choice — choosing to be hidden forgets it —; `of()` reads the choices of those of the given
members who appear, by reference; `eraseMember()` forgets one.

## `LeagueStore`

- `tier()` reads a member's tier on a board, null before their first settled week.
- `join()` places a member in a division of a tier for a week — the lowest numbered of their cohort holding fewer than
  the size, or the number after the highest of the tier — and returns the membership with its cohort; a member already
  placed that week keeps theirs. A unique key on board, week and member, written by an insertion that writes nothing
  when the row is there, places a member once.
- `membership()`, `division()` and `memberships()` read memberships by tier name, by division, then by the moment they
  joined and the member's reference.
- `isSettled()` and `unsettled()` say which weeks are settled, the unsettled ones from the earliest code.
- `settle()` writes every movement, every new tier and the week's mark together, and returns false, writing nothing,
  when the week is already settled — a unique key on board and week, inserted first in one transaction.
- `tiersOf()`, `membershipsOf()` and `eraseMember()` read and remove a member's tiers and memberships.

## Scores in Valkey

`Store\Valkey\ValkeyScoreStore` keeps the contributions and the scores in Valkey, or in a Redis server that speaks the
same commands, through a `Contract\ValkeyConnection`: one method that sends a command and returns its reply.
`RawCommandConnection` wraps the client the application already has — the `Redis` object of PHP's `redis` extension,
Relay, or Predis —:

```php
$store = new ValkeyScoreStore(new RawCommandConnection($redis), $boards, prefix: 'leaderboards');
```

Each ranking is a sorted set whose keys all have the score 0 and sort as the board ranks, by `Ranking::key()`: the
first lines are read by `ZRANGE`, a member's rank by `ZLEXCOUNT` — the keys before theirs, or before their score and
tie-breaks alone for a shared rank —, and how many are ranked by `ZCARD`, each in a logarithm of the ranking's size. A
ranking among a circle, or asked in directions other than the board's, is ordered in memory from the scores of its
members. Each write that touches several keys runs as one Lua script; every key starts with the prefix between braces,
a hash tag that keeps them on one node of a cluster. The snapshots, the choices and the leagues stay in another store.
