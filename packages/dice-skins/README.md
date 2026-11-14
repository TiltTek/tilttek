# tilttek/dice-skins

**A member's dice appearance — skins, colours, materials and owned dice — applied to the roller, its numbers kept readable.**

A member chooses how their dice look: a skin for all their dice, another for their d20, or a die of a set they own in
their collection. The library gives the roller, for each die it shows, the appearance to draw — the colour of the
faces, the colour of the numbers and pips, the material, the texture — as CSS custom properties, data attributes or
plain values for a renderer that reads JSON. The appearance never changes the randomness: it only dresses the dice the
server rolled.

Every skin keeps its numbers readable: its ink stands out from its faces at 4.5:1 at least, the contrast WCAG 2.2 asks
of text at level AA, and a skin under it is refused when it is defined. A die of a set gets the black or white ink
that stands out most on its colour. For a viewer who asks for high contrast, the numbers are kept at 7:1 at least, on
solid faces without texture, and a die that cannot reach it is shown black on white.

It is a plain PHP library. The skins come from the site — eight base skins ship with the library, and a site adds its
own from JSON —, the members' choices from a store the application provides, and the owned dice from
`tilttek/collections`, read through a port. An adapter gives the appearance to the roller of `tilttek/dice-bundle`.

## Status

| Part | State |
|---|---|
| `Color`, `Material`, `TextureReference`: colours with their WCAG luminance and contrast, materials, texture keys | Implemented and tested |
| `Skin`, `SkinLook`, `SkinDefinitions`, `BaseSkins`: readable skins, their looks by type of dice, read from JSON | Implemented and tested |
| `DiceSkins`, `DieAppearance`: the members' choices and the appearance of each die, in standard or high contrast | Implemented and tested |
| `Contract\SkinCatalog`, `Contract\SkinChoiceStore`, `Contract\OwnedDice`, with memory stores | Implemented and tested |
| `Collections\ShelfOwnedDice`: the owned dice read from `tilttek/collections` | Implemented and tested |
| `DiceBundle\DiceSkinsAppearance`: the appearance given to the roller of `tilttek/dice-bundle` 0.2 | Implemented and tested |
| Exceptions under one `DiceSkinsException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | Skins, choices, owned dice and the appearance given to the roller. |
| [Accessibility](doc/accessibility.md) | The contrast of the numbers, high contrast, and what the renderer keeps. |
| [Rolling with tilttek/dice-bundle](doc/dice-bundle.md) | The adapter of the roller's appearance port, and how to declare it. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;

$skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore());
$member = ResourceReference::of('member', '42');

$skins->choose($member, 'crimson');           // all their dice
$skins->choose($member, 'gold', 20);          // and their d20

$d20 = $skins->appearance($member, 20);
$d20->style();             // "--tilttek-dice-color: #d4a017; --tilttek-dice-ink: #1a1a1a"
$d20->dataAttributes();    // ['data-dice-appearance' => 'Gold', 'data-dice-sides' => '20', 'data-dice-material' => 'metal']

$skins->appearance($member, 6, Accessibility::HighContrast)->toArray();
```

## Requirements

PHP 8.5 or later, `phil/resource` and `tilttek/collections` 0.1; `tilttek/dice-bundle` 0.2.0-alpha or later for its
roller's appearance.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
