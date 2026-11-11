# The catalog

A `Catalog` holds what a site's collection is made of, each definition by code: 1 to 64 lower-case letters, digits,
`_`, `.` or `-`, starting with a letter.

## Rarities

A `Rarity` is a code and a weight, 1 to 10^6: the share of the draws that land on it when a pack or the calendar says
nothing else. The catalog keeps its rarities in the order they are declared, from the most common to the rarest.

## Items

An `Item` has a code, a name as `Texts` — a text by locale, `en`, `fr`, `fr_CA`, read in the reader's locale, then its
language, then English, then the first written —, a rarity, up to 20 sets, an optional description and image (a path
or a URL without whitespace), whether its copies may change hands, and the `CollectibleDie` it is for the items of a
set of dice: 2 to 1,000 sides, a colour written `#rrggbb` and a material.

## Sets

An `ItemSet` has a code, a name, a `SetKind` and up to 20 `Reward`s. The kind says how the set comes to a member:

| Kind | How |
|---|---|
| `plain` | Item by item: the item of the day, packs, rewards |
| `base` | Every member gets it on arrival, through `Collections::welcome()` |
| `earned` | For the site's points, through the catalog of `tilttek/exchange`; given whole when the exchange is confirmed |
| `paid` | Bought for oneself or as a gift; given whole when the site confirms the purchase |

A reward is declared, never executed: its kind — `points`, `badge`, `item`, or any the application branches —, what
it names in that kind, and how many. A set may hold no item yet; it is never complete.

## Packs

A `Pack` has a code, a name, a size of 1 to 50, the sets it draws from — every item when none is named — and the
weights of its rarities when they differ from the catalog's. A rarity left out of a pack's weights is never drawn from
it.

## From plain values

`CatalogReader::read()` and `FixedCatalog::fromArray()` read the definition a configuration holds:

```php
[
    'rarities' => ['common' => 70, 'rare' => ['weight' => 25], 'legendary' => 5],
    'items' => [
        'pirate' => ['name' => ['en' => 'Pirate', 'fr' => 'Pirate'], 'rarity' => 'rare', 'sets' => ['crew'], 'image' => '/img/pirate.webp'],
        'ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#c0392b', 'material' => 'resin']],
        'medal' => ['name' => 'Medal', 'rarity' => 'rare', 'tradable' => false, 'description' => 'For the bravest.'],
    ],
    'sets' => [
        'crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]],
        'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
    ],
    'packs' => ['booster' => ['name' => 'Booster', 'size' => 5, 'sets' => ['crew'], 'weights' => ['common' => 80, 'rare' => 20]]],
]
```

Unknown keys are refused, so that a typo never passes for a default. The catalog checks every reference — an item's
rarity and sets, a pack's sets and weights — and that a pack has something to draw.

## A catalog kept elsewhere

The services ask a `CatalogProvider` for the catalog at each operation. `FixedCatalog` holds one built once; a
`WritableCatalog` is edited one definition at a time, `define(DefinitionKind, code, definition)` and
`remove(DefinitionKind, code)`, the catalog that results checked whole before anything is written.
`tilttek/collections-bundle` keeps one in the database.

## Odds

`Catalog::odds(sets, weights)` gives the `Odds` of a draw among the items of some sets: the rarities with an item in
the pool and a weight, the chance of each rarity — its weight over theirs — and of each item — its rarity's chance
shared among the items of that rarity —, and `draw(Randomizer)`, which picks a rarity by weight, then an item of it.
