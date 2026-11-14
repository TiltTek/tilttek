<!-- phil-dev source-sha256=9ad6ec45c28b5cdb8aeac2fc607b694b7e9c14ac82f0c1b6e8fde62e136f3598 -->
# Référence

| Type | Rôle |
|---|---|
| `DiceSkins` | Les choix des membres, les dés qu'ils possèdent et l'apparence de chaque dé |
| `DieAppearance` | L'apparence d'un dé : son nom, son aspect, son origine ; en propriétés CSS personnalisées, en attributs de données ou en valeurs simples |
| `AppearanceSource` | `Owned`, `ChosenForSides`, `ChosenForAll` ou `Default` |
| `Accessibility` | `Standard` ou `HighContrast` |
| `Skin` | Un skin qu'un site offre : clé, libellé, aspect, et aspects par nombre de faces ; ses chiffres à 4,5:1 au moins |
| `SkinLook` | La couleur des faces et de l'encre, la matière et la texture ; son contraste |
| `SkinDefinitions` | Lit des skins à partir de valeurs simples ou de JSON |
| `BaseSkins` | Les huit skins que la bibliothèque fournit, et celui à contraste élevé |
| `SkinChoice` | Le skin qu'un membre a choisi, pour un nombre de faces ou pour tous ses dés |
| `OwnedDie` | Un dé d'un ensemble qu'un membre possède, et son aspect |
| `Color` | Une couleur sRGB : sa luminance, son contraste avec une autre, la meilleure encre dessus |
| `Material` | Ce qu'un dé semble être fait, et la matière que nomme un texte libre |
| `TextureReference` | La clé d'une texture que le moteur de rendu résout |
| `Contract\SkinCatalog` | Les skins que le site offre |
| `Contract\SkinChoiceStore` | Où les choix des membres sont gardés |
| `Contract\OwnedDice` | Les dés qu'un membre possède |
| `Store\FixedSkinCatalog` | Les skins donnés une fois, les skins de base avec ceux du site |
| `Store\InMemorySkinChoiceStore` | Les choix en mémoire, pour les tests et les scripts |
| `Store\NoOwnedDice` | Personne ne possède de dés |
| `Collections\ShelfOwnedDice` | Les dés possédés, lus dans l'étagère de `tilttek/collections` |
| `DiceBundle\DiceSkinsAppearance` | L'apparence donnée au lanceur de `tilttek/dice-bundle`, par son port `DiceAppearance` |

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\DiceSkinsException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidSkin` | Une couleur, une clé, un libellé, une texture, un nombre de faces ou une définition que la bibliothèque refuse, ou un aspect dont les chiffres ressortiraient à moins de 4,5:1 |
| `Exception\SkinRefused` | Un skin que le catalogue n'offre pas est choisi, ou est le skin par défaut — par l'adaptateur de `tilttek/dice-bundle` aussi |

Chaque message dit ce qui était attendu, comme `A colour is written #rgb or #rrggbb; "red" is not.`
