# Installation

## Requirements

- PHP 8.5 or later.
- `phil/resource`, whose references name the guilds and the members.
- `tilttek/quests`, whose `TeamMembership` contract `GuildTeamMembership` implements.
- A `psr/clock` clock, such as `symfony/clock`.
- Optionally `tilttek/leaderboards`, to rank a guild's members among themselves.

## Installing

```sh
composer require tilttek/guilds
```

## Wiring the library

`Guilds` takes the application's guild store and clock; `Goals` takes the goal store, the `Guilds` service and the
clock. Everything else has a default:

```php
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Limits;

$limits = new Limits(guildsPerMember: 10, requestsPerGuild: 100, openGoals: 10);

$guilds = new Guilds(
    $guildStore,                           // Contract\GuildStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\GuildObserver, told of each change
);

$goals = new Goals($goalStore, $guilds, $clock, $limits, [$observer]);
```

`Store\InMemoryGuildStore` and `Store\InMemoryGoalStore` serve tests and applications that rebuild their guilds at
each start; an application that keeps them implements the two contracts on its storage, as
[extension points](extension-points.md) describe.

`Guilds` and `Goals` keep no state between two calls: one instance serves every request, in a worker as in a request.
The stores and observers they are given keep that promise too, beside their storage.
