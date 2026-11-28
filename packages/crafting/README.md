# tilttek/crafting

**Item recipes kept as data, which combine or evolve items and show what is still missing before anything is
crafted.**

tilttek/crafting turns items into other items. A site writes its recipes as data — a configuration file, a database
row —, never as code: each recipe lists the ingredients it **consumes**, by an item's code or by a **tag** any item may
carry — `rare-metal` rather than one metal —, the **tools** it only needs held, the items it **produces** and those it
**gives back**, the empty bucket of a bucket of water. A recipe either **combines** items into new ones or **evolves**
one item into its next form, a dragon egg into a dragon, which an inventory may keep as the same object.

Before anything moves, a **preview** shows, ingredient by ingredient, the items set aside and what is still missing;
the crafter lists what a member can craft now and what they are **closest** to. A craft is made **once per key**, at
once or not at all: a request sent twice crafts once, and nothing moves when an item is missing. A recipe is always
deterministic, and an item the site protects — one bought for money — is never consumed.

It is for a site whose members gather items — a collection, a character's inventory, a larp's economy — and make
something of them; `tilttek/larp-crafting` builds on it for larps. It is a plain PHP library whose dependencies are
`phil/resource`, the opaque reference of a holder, and `psr/clock`; the items stay where the application keeps them,
behind the `Inventory` it implements. Holders are references the application gives: the library never reads a name, an
e-mail or an account, and its events carry identifiers only.

## Status

| Part | State |
|---|---|
| `Recipe`, `Ingredient`, `ItemQuantity`, `RecipeKind`: recipes that combine or evolve, ingredients by item or tag, tools, outputs and items given back | Implemented and tested |
| `RecipeBook`: the recipes read from plain values and written back, the recipes consuming or producing an item | Implemented and tested |
| `Crafter`, `Plan`, `Requirement`, `Selection`: previews, what is craftable now and closest, items chosen for a tag, crafts once per key | Implemented and tested |
| `Craft`, `Crafted`, `Contract\CraftObserver`: the craft made, and what is told, identifiers only | Implemented and tested |
| `Contract\Inventory`, `Holding`, with `Store\InMemoryInventory` | Implemented and tested |
| `Contract\ConsumptionPolicy`, with `Policy\ProtectedItems`: items no craft consumes | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Recipes](doc/recipes.md) | Ingredients, tools, outputs and items given back, evolving recipes, attributes, the book read from plain values, protected items. |
| [Crafting](doc/crafting.md) | Previews and what is missing, how items are set aside, choosing the items of a tag, crafts once per key, what is craftable and closest. |
| [Extension points](doc/extension-points.md) | The inventory, the consumption policy, observers and the clock. |
| [Exceptions](doc/exceptions.md) | `CraftingException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and bench, and testing code that crafts. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/crafting
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\Store\InMemoryInventory;

$book = RecipeBook::fromArray(['recipes' => [
    'healing-potion' => [
        'ingredients' => [
            ['item' => 'red-herb', 'quantity' => 2],
            ['tag' => 'clear-water'],
            ['item' => 'mortar', 'consumed' => false],
        ],
        'outputs' => [['item' => 'healing-potion']],
        'returns' => [['item' => 'empty-bucket']],
    ],
]]);

$inventory = new InMemoryInventory(['rain-water' => ['clear-water']]);
$crafter = new Crafter($book, $inventory, $clock);
$member = ResourceReference::of('member', '42');

$inventory->put($member, 'red-herb', 1);
$inventory->put($member, 'rain-water', 1);

$plan = $crafter->preview($member, 'healing-potion');
$plan->isCraftable();                       // false
$plan->missing();                           // 1 × red-herb, 1 × mortar

$inventory->put($member, 'red-herb', 1);
$inventory->put($member, 'mortar');

$crafter->craft($member, 'healing-potion', 'craft-7f3a');   // the herbs and the water are taken, the mortar stays
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
