# tilttek/sets

**Sets for badges, collections and other gamification: versioned definitions, tiers, completion from what a holder
owns, declared rewards and events, without granting anything.**

A set is a list of members to bring together — badges, cards, dice, stickers —, each named by a stable identifier and
shown in the set's order. A holder completes it by owning every member; tiers mark steps before or beyond that — *5 of
20*, *all 20 at level 2* —, so that a set redone at higher levels is one set with one tier per level. The library
measures a holder's holdings against a set — owned, missing, percentage, tiers reached —, records each completion once,
with the version of the set it was earned under, and tells it by an event that carries the rewards the set or the tier
declares. Rewards are opaque references: the application grants them, through the packages it has.

A changed set takes a new version, and a version, once defined, is never rewritten: a completion recorded under an
earlier version stays when the set grows, and the progress says so, for the application to invite the holder to
complete the new members.

It is the neutral brick under badge sets, collection sets and sticker sheets: it has no criterion, no rarity, no
inventory and no notion of what a member is. Holders are opaque resource references from `phil/resource`, never an
e-mail address; moments are in UTC. It is a plain PHP library, with ports for its stores and in-memory adapters.

## Status

| Part | State |
|---|---|
| `SetDefinition`, `Tier`, `Reward`: versioned sets, ordered members, tiers checked from easiest to hardest, opaque rewards, read from and written to arrays and JSON | Implemented and tested |
| `Holdings`, `Evaluation`: what a holder owns, at a level, measured against a set | Implemented and tested |
| `Sets`, `Progress`, `Completion`: versions defined, progress, completions recorded once and never taken back | Implemented and tested |
| `Event\SetCompleted`, `Event\TierReached`: told once, with the rewards declared | Implemented and tested |
| `Contract\DefinitionStore`, `Contract\CompletionStore` and their in-memory adapters | Implemented and tested |
| A database adapter | Not provided: an application implements the two stores |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Defining sets](doc/defining-sets.md) | Members, tiers, rewards, versions, and reading a definition from configuration or JSON. |
| [Progress and completions](doc/progress-and-completions.md) | Holdings, the evaluation, recording, the events, versions and erasure. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `SetsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\Sets\{Holdings, Reward, SetDefinition, Sets, Tier};
use Tilttek\Sets\Store\{InMemoryCompletionStore, InMemoryDefinitionStore};

$sets = new Sets(new InMemoryDefinitionStore(), new InMemoryCompletionStore(), events: $dispatcher);

$sets->define(new SetDefinition('starter-dice', 1, ['d4', 'd6', 'd8', 'd10', 'd12', 'd20'],
    tiers: [new Tier('half', required: 3), new Tier('shiny', level: 2, rewards: [new Reward('badge', 'shiny-dice')])],
    rewards: [new Reward('points', 'xp', 50)],
));

$holder = ResourceReference::of('member', '1042');
$progress = $sets->record('starter-dice', $holder, Holdings::of(['d4', 'd6', 'd20']));
$progress->evaluation->percent();      // 50
$progress->evaluation->missing;        // ['d8', 'd10', 'd12']
$progress->recorded;                   // the tier "half", told by Event\TierReached
```

## Requirements

PHP 8.5 or later; `phil/resource` 0.1, `psr/clock` and `psr/event-dispatcher`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
