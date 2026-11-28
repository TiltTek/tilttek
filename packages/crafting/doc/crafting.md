# Crafting

`Crafter` crafts from the book with what holders have. It reads a holder's items through the application's
`Contract\Inventory`: one `Holding` per item, with its quantity and the tags it carries.

## Previews and what is missing

`preview($holder, $recipe, $times, $selection)` returns a `Plan` and moves nothing. The plan holds one `Requirement`
per ingredient, in the order of the recipe: how many it `needed`, the items of the holder set aside for it in
`allocation`, `held()`, `missing()` and `isMet()`.

| Method of `Plan` | What it returns |
|---|---|
| `isCraftable()` | Whether every ingredient is met |
| `missing()` | The requirements not met, in the order of the recipe |
| `missingCount()`, `heldCount()` | How many items are missing, and held, for every ingredient |
| `consumed()`, `used()` | The items the craft takes, and the tools it needs held, in the order of their codes |
| `returned()`, `produced()` | The items it gives back and produces, repeated `$times` |

A recipe is repeated 1 to 100 times.

## How items are set aside

The crafter sets items aside ingredient by ingredient, and an item set aside for one serves no other:

1. the ingredients named by an item's code, in the order of the recipe;
2. the ingredients asked by a tag for which the member chose items;
3. the other ingredients asked by a tag, the one fewest held items carry first, ties in the order of the recipe.

An ingredient asked by a tag takes its items in the order of their codes. Serving the scarcest tags first keeps a
recipe that asks for "two metals" and "one rare metal" from spending the only rare metal on the metals. A protected
item never serves a consumed ingredient.

## Choosing the items of a tag

A `Selection` names, by the position of an ingredient in its recipe, the items a member chooses for it — which metals
go into the blade:

```php
use Tilttek\Crafting\Selection;

$crafter->craft($member, 'blade', 'craft-91c2', selection: new Selection([0 => ['copper' => 1, 'iron' => 1]]));
```

A selection chooses for ingredients asked by a tag only, 1 to 16 items each. The quantities chosen add up to what the
ingredient needs; a chosen item the holder has carries the tag, and is not protected when the ingredient is consumed.
A chosen item the holder lacks is counted as missing.

## Crafting once per key

`craft($holder, $recipe, $key, $times, $selection)` plans the craft and, when every ingredient is met, hands a `Craft`
to the inventory, which applies it at once or not at all: it removes what the craft consumes, adds what it gives back
and produces, and records it under its key.

The key — 1 to 191 visible ASCII characters, the identifier of the member's request — makes the craft count once. The
same key given again returns the craft it made, without crafting again, even once the items are gone; a key that made
another craft — another holder, recipe or number of repetitions — is refused. A craft with an item missing is refused
with what is missing, and nothing moves.

The `Craft` holds its key, its holder, its recipe, its repetitions, the items consumed, used, given back and produced,
its moment, and for an evolving recipe the item that evolved. Once applied, each `Contract\CraftObserver` is told a
`Crafted` — the holder, the recipe, the key and the moment, identifiers only. A craft found again under its key is not
told twice.

## Craftable and closest

`craftable($holder)` lists the plans of the recipes the holder can craft once now, in the order of the book.
`closest($holder, $limit)` lists the recipes the holder cannot craft yet but holds something for, the fewest items
missing first, ties in the order of the book — 1 to 50 of them, 10 by default: what to look for next. Each reads the
holder's items once for every recipe.

The crafter reads at most 10,000 holdings of a holder, one per item.

## Erasure

The crafts and the items live in the application's inventory, which erases a holder by its own means;
`Store\InMemoryInventory::forget()` removes a holder's items and crafts.
