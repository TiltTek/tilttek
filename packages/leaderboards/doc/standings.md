# Standings

## A board as one viewer sees it

```php
$standing = $leaderboards->standing('games-month', viewer: $member, period: '2026-10', limit: 20);
```

`standing()` reads the board in a period — the one under way when no code is given — and returns a `Standing`: the
board, the period, its first `limit` lines (1 to 100, 10 by default) as `Row`s, the viewer's own line, and how many
members are ranked. Each `Row` holds its rank and score, and who holds them as the viewer may know it
([Appearing on a board](appearance.md)).

The viewer's own line is there even when it is far below the first ones; `Standing::ownIsListed()` says whether it is
also among them, so that a template shows it once. A viewer without a score has no own line, and an anonymous standing
— no viewer — has none either.

## A member's rank

`rank()` returns the member's own line on a board in a period, or null when they hold no score there.

## Ties and tie-breaks

Lines with the same score are ordered by the board's tie-breaks, then by the first to reach the score, or share a rank
when the board's ties are `shared` and the lines are equal on the score and every tie-break; see
[Boards and periods](boards-and-periods.md#ties). A shared rank skips as many places after it: 1, 1, 3.

## A ranking by object

On a board ranked by object, each object — a game, a book, a level — has its own ranking, which `standing()`, `rank()`,
`snapshot()`, `frozen()` and `teams()` read by the object they are given:

```php
$standing = $leaderboards->standing('game-records', $member, object: ResourceReference::fromString('game:catan'));
```

A board ranked by object asked without an object, or another board asked with one, raises `InvalidLeaderboard`.

## Circles

A ranking between friends or inside a group — a club, a table — is a filter on the members, given by the application
as a `Circle` of 1 to 1000 members:

```php
$friends = Circle::of('member:44', 'member:7', 'member:9');
$standing = $leaderboards->standing('games-month', $member, among: $friends);
```

The members are ranked among themselves — the first of the circle ranks first —, the participants are those of the
circle, and a viewer outside the circle has no own line. Tilttek keeps no friendship and no group: the application
knows them.

## Unknown boards and periods

An unknown board raises `UnknownBoard`; a code the board's schedule does not know, or the current period of a board
between two seasons, raises `UnknownPeriod`.
