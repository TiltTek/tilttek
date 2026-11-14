# Usage

## Skins

A `Skin` has a key, a label, the `SkinLook` of its dice and, for some types of dice, a look of their own by number of
sides. A look is the colour of the faces, the colour of the numbers and pips — the ink —, a `Material` and, if any, a
`TextureReference`.

The library ships eight base skins, `BaseSkins::all()`: `ivory` — the default —, `obsidian`, `crimson`, `forest`,
`ocean`, `amethyst`, `gold` and `high-contrast`. A site adds its own from JSON, as `Skin::toArray()` writes them:

```json
[
    {"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#ffffff", "material": "pearl",
     "texture": "pearl/soft", "types": {"20": {"face": "#1b1b1f", "ink": "#f5f5f5"}}}
]
```

```php
$catalog = FixedSkinCatalog::withBase($json);        // the base skins, then the site's
$catalog = new FixedSkinCatalog(SkinDefinitions::fromJson($json));   // the site's only
```

The look of a type takes from the skin's look what it does not say. A colour is `#rgb` or `#rrggbb`; a material is
one of `solid`, `translucent`, `pearl`, `glitter`, `marble`, `metal`, `wood`, `stone` and `gem`; a texture is a key the
renderer resolves to an image of its own, never an address. `SkinDefinitions` refuses, with `InvalidSkin`, a field it
does not know, a value of the wrong type, two skins of the same key, a look whose numbers stand out at less than 4.5:1,
more than 256 skins or 32 types a skin, and a text over 256 KiB or deeper than 8 levels.

## Choices

```php
$skins->choose($member, 'ember');         // for all the member's dice
$skins->choose($member, 'gold', 20);      // for their d20
$skins->choices($member);                 // the one for all their dice first, then by number of sides
$skins->clear($member, 20);               // back to the skin for all their dice
$skins->eraseMember($member);             // every choice forgotten
```

`choose()` refuses a skin the catalog does not offer with `SkinRefused`, and a number of sides outside 2 to 1000 with
`InvalidSkin`. A skin the site later stops offering is passed over: the member's dice fall back to the next choice.

## Owned dice

A member who owns dice in `tilttek/collections` chooses one for its number of sides on their shelf, with
`DiceShelf::choose()`. `DiceSkins::owned()` lists the dice they own, through the `Contract\OwnedDice` port, for the page
where they choose. A die traded away is no longer shown: the shelf no longer gives it.

## The appearance of a die

`appearance($member, $sides)` gives the first of:

1. the die of a set the member owns and chose for that number of sides — its colour, with the black or white ink that
   stands out most, and the material its collection names; a die its collection gives no colour takes the look of the
   next level, with its own name;
2. the skin the member chose for that number of sides;
3. the skin the member chose for all their dice;
4. the site's default skin, which the catalog must offer.

The `DieAppearance` tells where it comes from, `$source`, and gives the renderer:

| Method | Gives |
|---|---|
| `cssProperties()` | `--tilttek-dice-color` for the faces and `--tilttek-dice-ink` for the numbers |
| `style()` | the same, as the value of a `style` attribute |
| `dataAttributes()` | `data-dice-appearance`, `data-dice-sides`, `data-dice-material`, and `data-dice-texture` and `data-dice-contrast="high"` when they apply |
| `toArray()` | everything as plain values, the contrast ratio included, for a renderer that reads JSON |

In Twig, the attributes go through `html_attr`, which escapes them, the name of the skin included:

```twig
<span class="die" style="{{ appearance.style }}" {{ html_attr(appearance.dataAttributes) }}>{{ face }}</span>
```

A Stimulus controller that draws the dice reads the same values; the library ships no script.
