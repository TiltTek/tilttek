# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Contract\SkinCatalog` | The skins the site offers: from a file, a back office, a database | `Store\FixedSkinCatalog` |
| `Contract\SkinChoiceStore` | The storage of the members' choices | `Store\InMemorySkinChoiceStore` |
| `Contract\OwnedDice` | The dice a member owns, and the one they chose for a number of sides | `Collections\ShelfOwnedDice`, `Store\NoOwnedDice` |
| The default skin | The key of the skin a member who chose nothing gets | `BaseSkins::DEFAULT`, `ivory` |

Every point is passed to the constructor of `DiceSkins`, which keeps nothing between two calls.

The library also fills an extension point of another package: `DiceBundle\DiceSkinsAppearance` implements the
`DiceAppearance` port of `tilttek/dice-bundle`, as [Rolling with tilttek/dice-bundle](dice-bundle.md) explains.

## Storing the choices

A `SkinChoiceStore` keeps one choice per member for all their dice — `$sides` null — and one per member and number of
sides:

- `save()` replaces the member's choice for the same number of sides, or for all their dice;
- `find()` gives it, or null;
- `remove()` returns whether there was one;
- `ofMember()` lists the choice for all the dice first, then the others by number of sides;
- `eraseMember()` removes every choice of the member and returns how many, for the erasure of a member.

`SkinChoice::toArray()` gives the row to keep: the member as `kind:identifier`, the skin key and the number of sides.

## Owned dice from another source

An application whose members own dice elsewhere implements `Contract\OwnedDice`: `ownedBy()` lists `OwnedDie`s — the
item, its name, its number of sides, its colour, its material as free text and the member's copy —, and `chosen()`
gives the die the member rolls for a number of sides, or null.
