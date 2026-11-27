# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/dice-skins` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-20

### Changed

- `tilttek/collections` 0.2 required: 0.1 is no longer accepted.

### Fixed

- `Skin` refuses with `InvalidSkin`, rather than a `TypeError`, types that are not `SkinLook` values under whole
  numbers of sides, and a label that is not UTF-8.

## 0.1.0-alpha - 2026-11-14

### Added

- `DiceSkins`: the skin a member chooses for all their dice or for one number of sides, a choice refused for a skin the
  catalog does not offer, the choices listed, cleared and erased; the appearance of each die, found in the die of a set
  the member owns and chose, then the skin chosen for its number of sides, the skin chosen for all their dice and the
  site's default, a skin no longer offered passed over; in high contrast, the look kept at 7:1, its ink changed, or
  black on white, always solid and without texture.
- `DieAppearance` and `AppearanceSource`: the name, the look and the source of a die's appearance, as the CSS custom
  properties `--tilttek-dice-color` and `--tilttek-dice-ink`, a `style` value, data attributes and plain values with
  the contrast ratio.
- `Skin`, `SkinLook`, `SkinDefinitions` and `BaseSkins`: skins of a key, a label, a look and looks by number of sides,
  their numbers at 4.5:1 at least; read from plain values or JSON within 256 skins, 32 types a skin, 256 KiB and 8
  levels, a field not known refused, and written back; eight base skins, `ivory` the default, and the high-contrast
  one.
- `Color`, `Material` and `TextureReference`: sRGB colours from `#rgb` or `#rrggbb` with their WCAG 2.2 luminance,
  contrast and best ink; nine materials and the material a free text names; texture keys that never leave the folder
  of the textures.
- `SkinChoice` and `OwnedDie`: a member's choice, and a die of a set they own with the black or white ink that stands
  out most on its colour.
- `Contract\SkinCatalog`, `Contract\SkinChoiceStore` and `Contract\OwnedDice`, with `Store\FixedSkinCatalog`,
  `Store\InMemorySkinChoiceStore` and `Store\NoOwnedDice`; `Collections\ShelfOwnedDice`, the owned dice read from the
  shelf of `tilttek/collections`.
- `DiceBundle\DiceSkinsAppearance`: the adapter of the `DiceAppearance` port of `tilttek/dice-bundle` 0.2.0-alpha, the
  appearance of a member's die given to its roller with its name, the colours of its faces and of its numbers, its
  material and its texture key, in standard or high contrast as the viewer's accessibility the roller passes asks, or
  as the adapter was built without it; no appearance for a number of sides no die has.
  `tilttek/dice-bundle` is suggested.
- A conflict with `tilttek/dice-bundle` older than 0.2: the adapter uses its 0.2 API.
- `Exception\DiceSkinsException`, with `InvalidSkin` and `SkinRefused`.
- A fuzz target for the skin definitions, and benchmarks of an appearance and of a catalog of 256 skins.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
