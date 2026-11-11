# tilttek/badges

**Badges your members earn by what they do and show when they choose.**

tilttek/badges awards badges and achievements from the members' activity, recorded by `tilttek/activity`. A badge has
a criterion read from the journal — a number of activities, a total of their quantities, a first time, days in a row,
a period, a combination of criteria, a value the application measures, or an award by hand —, and the progress towards
it is computed and shown, *7 of 10*. Tiers link the badges of a series — bronze, silver, gold —; a repeatable badge is
earned again each time its target is reached again; a revocable badge is withdrawn when the activity that earned it is
cancelled; a hidden achievement is shown, and its criterion with it, only once earned. When a rule changes, the badges
are recomputed from the journal over the period the application chooses.

A badge earned is private until the member shows it. The member chooses a title worn beside their name, the showcase
of their profile and its order, what they share and where, and which other applications may read their badges, a
consent they withdraw at any time. Erasing a member removes their awards, the contributions of their activity, their
profile and their consents. `tilttek/badges-bundle` issues a badge as an Open Badges 3.0 credential, at the member's
request, through `phil/open-badges`.

It is for an application that recognises what its members do — a community site, a game site, a store — and wants
badges it can recompute and members who decide what is public. `tilttek/badges-bundle` integrates it with Symfony and
keeps the badges in Doctrine DBAL; an application without Symfony takes the library alone, with its stores in memory or
its own.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal the criteria read, `phil/resource`,
the opaque reference of a member, `psr/clock`, and the `mbstring` extension. Members are references the application
gives, `member:44`: the library never reads a name, an e-mail or an account.

The design is small: a `Badge` with its `Criterion`, held by a `Catalog`; `BadgeProjection` reads the journal into
`Contribution`s and `Award`s through four stores; `Evaluator` computes a `Progress`; `Badges` serves the application —
awards, progress, `Profile`, `Showcase`, sharing, consents, erasure.

## Status

| Part | State |
|---|---|
| `Badge`, `Tier`, `Catalog`: codes, texts, images, tiers, repeatable, hidden and revocable badges, a catalog from plain values | Implemented and tested |
| Criteria: `Count` (and first times), `Total`, `Streak`, `Measure`, `Manual`, `AtLeast` (all or some), periods and context conditions in `Scope`, `CriterionReader` | Implemented and tested |
| `BadgeProjection` and `Evaluator`: contributions once per activity, awards and repeats, withdrawals, rebuilds without announcing again | Implemented and tested |
| `Badges`: awards, progress, overview, manual awards, profile, showcase, title, sharing, consents of other applications, erasure | Implemented and tested |
| Stores — `ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` — and their implementations in memory | Implemented and tested |
| Exceptions under one `BadgesException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Badges and criteria](doc/criteria.md) | Badges, tiers, every kind of criterion, periods, context conditions, the catalog from plain values. |
| [Awarding](doc/awarding.md) | The projection, progress, repeatable and revocable badges, manual awards, rebuilds, observers. |
| [What a member shows](doc/showing.md) | Private badges, the showcase, the title, sharing, the consent of other applications. |
| [Erasure](doc/erasure.md) | What erasing a member removes. |
| [Writing a store](doc/writing-a-store.md) | The contracts of the four stores an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: stores, measures, criteria, observers, sharing, the clock. |
| [Exceptions](doc/exceptions.md) | `BadgesException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that awards badges. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/badges
```

```php
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;

$catalog = Catalog::fromArray([
    'commenter' => ['name' => 'Commenter', 'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 10]]],
    'regular' => ['name' => 'Regular', 'criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]],
], 'America/Toronto');
$progress = new InMemoryProgressStore();
$awards = new InMemoryAwardStore();
$evaluator = new Evaluator($catalog, $progress);

$projection = new BadgeProjection($evaluator, $progress, $awards); // handed to tilttek/activity's projections
$badges = new Badges($evaluator, $progress, $awards, new InMemoryProfileStore(), new InMemoryGrantStore(), $clock);

$badges->progress($member, 'commenter'); // 7 of 10
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
