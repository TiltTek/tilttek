# Reference

| Type | Role |
|---|---|
| `DiceSkins` | The members' choices, the dice they own, and the appearance of each die |
| `DieAppearance` | The appearance of a die: its name, its look, its source; as CSS custom properties, data attributes or plain values |
| `AppearanceSource` | `Owned`, `ChosenForSides`, `ChosenForAll` or `Default` |
| `Accessibility` | `Standard` or `HighContrast` |
| `Skin` | A skin a site offers: key, label, look, and looks by number of sides; its numbers at 4.5:1 at least |
| `SkinLook` | The colour of the faces and of the ink, the material and the texture; its contrast |
| `SkinDefinitions` | Reads skins from plain values or JSON |
| `BaseSkins` | The eight skins the library ships, and the high-contrast one |
| `SkinChoice` | The skin a member chose, for one number of sides or for all their dice |
| `OwnedDie` | A die of a set a member owns, and its look |
| `Color` | An sRGB colour: its luminance, its contrast with another, the best ink on it |
| `Material` | What a die seems to be made of, and the material a free text names |
| `TextureReference` | The key of a texture the renderer resolves |
| `Contract\SkinCatalog` | The skins the site offers |
| `Contract\SkinChoiceStore` | Where the members' choices are kept |
| `Contract\OwnedDice` | The dice a member owns |
| `Store\FixedSkinCatalog` | The skins given once, the base skins with the site's |
| `Store\InMemorySkinChoiceStore` | The choices in memory, for tests and scripts |
| `Store\NoOwnedDice` | Nobody owns any dice |
| `Collections\ShelfOwnedDice` | The owned dice read from the shelf of `tilttek/collections` |
| `DiceBundle\DiceSkinsAppearance` | The appearance given to the roller of `tilttek/dice-bundle`, through its `DiceAppearance` port |

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\DiceSkinsException` | The interface of every exception of the library |
| `Exception\InvalidSkin` | A colour, key, label, texture, number of sides or definition the library refuses, or a look whose numbers would stand out at less than 4.5:1 |
| `Exception\SkinRefused` | A skin the catalog does not offer is chosen, or is the default — through the adapter of `tilttek/dice-bundle` too |

Each message says what was expected, as `A colour is written #rgb or #rrggbb; "red" is not.`
