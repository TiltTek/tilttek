# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/activity`, the journal of the members' activity the boards read.
- `phil/resource`, the opaque reference of a member, and `psr/clock`, the clock the current period is read from.

## Installing

```sh
composer require tilttek/leaderboards
```

A Symfony application installs `tilttek/leaderboards-bundle` instead, which brings the library, its Doctrine DBAL
stores, the configuration of the boards, the commands and the templates.

## Wiring the library by hand

The boards, the three stores and a clock make `Leaderboards`; the boards and the score store make the projection, which
the journal's projections keep up to date:

```php
$boards = Boards::fromArray($configuration, 'America/Toronto');
$scores = new InMemoryScoreStore();
$projection = new LeaderboardProjection($boards, $scores);
$leaderboards = new Leaderboards($boards, $scores, new InMemorySnapshotStore(), new InMemoryAppearanceStore(), $clock, $observer);

$journal = new Journal($log, $clock, observers: [new LiveProjections($projector, new Projections([$projection]))]);
```

The stores in memory forget everything with the process: an application writes its own on its storage
([Writing a store](writing-a-store.md)), or takes those of the bundle.
