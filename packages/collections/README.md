# tilttek/collections

**Items to collect — cards, dice, figurines, costumes —, their rarity and sets, the item of the day and packs whose odds are published.**

tilttek/collections keeps what the members of a site collect. A catalog declares rarities — `common`, `rare`,
`legendary` — with their weights, items with a translated name and description, an image, a rarity and the sets they
belong to, sets worth their declared rewards once a member owns every item of them, and packs. A member's copies come
under a key that makes each one count once: the base sets on arrival, a set earned for points or bought whole, a
reward, a gift, the item of the day — one item a day, from a calendar computed of fixed dates, dates of the
application's own rules and a draw weighted by rarity on the free days, given on the members' activity recorded by
`tilttek/activity` —, or a pack, several items drawn by rarity with published odds, from a seed committed by the site
and a client seed given by the member, which let anyone replay the draw. Doubles are counted, the album shows *12 of 20*, a set completed for the first time is told with its
rewards, a member chooses which of their dice the dice roller shows, copies move between members for a trade, and
erasing a member removes what the collections kept of them.

It is for a site that gives its members something to collect — a community site's mascots in costume, virtual trading
cards, the dice of a set. `tilttek/collections-bundle` integrates it with Symfony and keeps everything in Doctrine
DBAL; an application without Symfony takes the library alone and writes its own stores, or uses those in memory.

It is a plain PHP library whose dependencies are `tilttek/activity`, the journal the item of the day is claimed on,
`phil/resource`, the opaque reference of a member, `psr/clock`, and the `mbstring` and `sodium` extensions. Members are
references the application gives, `member:44`: the library never reads a name, an e-mail or an account. It depends on
no other gamification package: rewards are declared and the application gives them, and `tilttek/exchange` reaches the
copies through the inventory the bundle provides.

The design is small: a `Catalog` holds `Rarity`, `Item`, `ItemSet` and `Pack` definitions, read by `CatalogReader`
from plain values and provided by a `CatalogProvider`; `Collections` gives `Copy` objects under a `CollectionKey`,
moves them, takes them back and builds the `Album`; `Packs` commits a `Pack` into a `PendingOpening`, then opens it into a `PackOpening` from the `Odds`;
`DailyCalendar`, `Daily` and `DailyProjection` give the item of the day; `DiceShelf` keeps the dice a member chose;
`MemberData` exports and erases a member.

## Status

| Part | State |
|---|---|
| `Names`, `Texts`, `Rarity`, `CollectibleDie`, `Reward`, `Item`, `SetKind`, `ItemSet`, `Pack`: the definitions, translated, checked | Implemented and tested |
| `Catalog`, `CatalogReader`, `CatalogProvider`, `FixedCatalog`, `WritableCatalog`, `DefinitionKind`, `Odds`: the catalog from plain values, its references checked, the odds of a draw and the draw | Implemented and tested |
| `CollectionKey`, `Source`, `Copy`, `CopyMove`, `Collections`: copies given once per key, sets given whole, base sets on arrival, moves of a trade, copies taken back | Implemented and tested |
| `Album`, `Holding`, `SetProgress`, `SetCompletion`, `SetCompleted`, `CollectionObserver`: the album, doubles, progress and sets completed once with their rewards | Implemented and tested |
| `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening`, `Pack\SeedSource`, `Pack\SecureSeeds`: packs committed, then opened from the site's seed and the member's client seed, published odds and verification | Implemented and tested |
| `Daily\DailyCalendar`, `Daily\DateRule`, `Daily\FixedDate`, `Daily\DailyPick`, `Daily\PickOrigin`, `Daily\Days`: the computed calendar of the item of the day, generated and corrected | Implemented and tested |
| `Daily\Daily`, `Daily\DailyProjection`: the item of the day claimed once per member and day on the activity, cancellations and rebuilds | Implemented and tested |
| `Dice\DiceShelf`, `Dice\DieChoice`: the dice a member owns and the one they roll for each number of sides | Implemented and tested |
| The five stores with their in-memory implementations and the contracts any store passes | Implemented and tested |
| `MemberData`, `Erasure`: the export and erasure of a member | Implemented and tested |
| Exceptions under one `CollectionsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [The catalog](doc/catalog.md) | Rarities, items, sets, packs, the definition a configuration holds, and a catalog kept in a database. |
| [Copies and the album](doc/copies-and-album.md) | Keys, sources, bound copies, sets given whole, base sets, the album, doubles, sets completed and their rewards, moves and copies taken back. |
| [The item of the day](doc/item-of-the-day.md) | The computed calendar, its rules and its draw, generating and correcting it, claiming the item, the projection. |
| [Packs](doc/packs.md) | Published odds, opening a pack, seeds, commitments and verification. |
| [Dice](doc/dice.md) | The dice of a set, the die a member chooses, and the dice roller. |
| [Erasure](doc/erasure.md) | What exporting and erasing a member covers. |
| [Writing a store](doc/writing-a-store.md) | The five store contracts an application's storage implements. |
| [Extension points](doc/extension-points.md) | What an application gives the library: the catalog, stores, date rules, seeds, observers, the clock. |
| [Exceptions](doc/exceptions.md) | `CollectionsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that reads the collections. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/collections
```

```php
use Tilttek\Collections\Collections;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;

$catalog = FixedCatalog::fromArray([
    'rarities' => ['common' => 70, 'rare' => 25, 'legendary' => 5],
    'items' => [
        'deckhand' => ['name' => ['en' => 'Deckhand', 'fr' => 'Matelot'], 'rarity' => 'common', 'sets' => ['crew']],
        'captain' => ['name' => 'Captain', 'rarity' => 'legendary', 'sets' => ['crew']],
    ],
    'sets' => ['crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]]],
]);
$collections = new Collections($catalog, new InMemoryCopyStore(), new InMemoryCompletionStore(), $clock);

$collections->grant($member, 'deckhand', Source::Purchase, 'order:2026-0193:line:1');
$collections->album($member)->set('crew')?->total(); // 2
```

## Requirements

PHP 8.5 or later, with the `mbstring` and `sodium` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
