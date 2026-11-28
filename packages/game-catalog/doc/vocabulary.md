# Vocabulary

The library names the types of entries and the links between them after the data model of
[OpenTabletop](https://github.com/tabletop-commons/opentabletop), an open specification of board game data, whose
specification and documentation are published under the
[Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/) licence. The terms below marked
"OpenTabletop" are taken from it, as values; the others are the library's own.

## Types of entries

| `EntryType` | Value | From |
|---|---|---|
| `BaseGame` | `base_game` | OpenTabletop |
| `Expansion` | `expansion` | OpenTabletop |
| `StandaloneExpansion` | `standalone_expansion` | OpenTabletop |
| `Promo` | `promo` | OpenTabletop |
| `Accessory` | `accessory` | OpenTabletop |
| `FanExpansion` | `fan_expansion` | OpenTabletop |
| `Scenario` | `scenario` | The library: an adventure, a module or a campaign of a role-playing game, with its levels |
| `Supplement` | `supplement` | The library: a sourcebook of rules, setting or options |

## Links

| `LinkType` | Value | From |
|---|---|---|
| `Expands` | `expands` | OpenTabletop |
| `Reimplements` | `reimplements` | OpenTabletop |
| `Contains` | `contains` | OpenTabletop |
| `Requires` | `requires` | OpenTabletop |
| `Recommends` | `recommends` | OpenTabletop |
| `IntegratesWith` | `integrates_with` | OpenTabletop; read in both directions |
| `EditionOf` | `edition_of` | The library: another edition of the same game |
| `TranslationOf` | `translation_of` | The library: the same edition in another language |

## Votes

The rating of 1 to 5 by number of players, and the three choices kept beside it — best, recommended, not
recommended —, follow the player-count model of OpenTabletop; the thresholds of `topPlayerCounts()` and
`recommendedPlayerCounts()` are the library's. The weight from 1 to 5 is the scale OpenTabletop uses for complexity.
`GameKind`, `LanguageDependence`, the minimum age voted, the levels of a scenario and the collection statuses are the
library's own.
