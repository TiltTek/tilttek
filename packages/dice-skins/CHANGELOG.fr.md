<!-- phil-dev source-sha256=b263cb3d3f049a4ef0fa299f832d5660bab88008541362269716aba579553693 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-skins` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Modifié

- `tilttek/collections` 0.2 requis : 0.1 n'est plus accepté.

### Corrigé

- `Skin` refuse avec `InvalidSkin`, plutôt qu'avec une `TypeError`, des types qui ne sont pas des valeurs `SkinLook`
  sous des nombres entiers de faces, et un libellé qui n'est pas en UTF-8.

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `DiceSkins` : l'habillage qu'un membre choisit pour tous ses dés ou pour un nombre de faces, un choix refusé pour un
  habillage que le catalogue n'offre pas, les choix énumérés, vidés et effacés ; l'apparence de chaque dé, trouvée dans
  le dé d'un ensemble que le membre possède et a choisi, puis dans l'habillage choisi pour son nombre de faces,
  l'habillage choisi pour tous ses dés et celui du site par défaut, un habillage qui n'est plus offert étant sauté ; en
  contraste élevé, l'allure gardée à 7:1, son encre changée, ou du noir sur du blanc, toujours unie et sans texture.
- `DieAppearance` et `AppearanceSource` : le nom, l'allure et la source de l'apparence d'un dé, sous forme des
  propriétés personnalisées CSS `--tilttek-dice-color` et `--tilttek-dice-ink`, d'une valeur `style`, d'attributs de
  données et de valeurs simples avec le rapport de contraste.
- `Skin`, `SkinLook`, `SkinDefinitions` et `BaseSkins` : des habillages avec une clé, un libellé, une allure et des
  allures par nombre de faces, leurs chiffres à au moins 4,5:1 ; lus à partir de valeurs simples ou de JSON dans les
  limites de 256 habillages, 32 types par habillage, 256 Kio et 8 niveaux, un champ inconnu refusé, et réécrits ; huit
  habillages de base, `ivory` par défaut, et celui à contraste élevé.
- `Color`, `Material` et `TextureReference` : des couleurs sRGB à partir de `#rgb` ou de `#rrggbb` avec leur luminance,
  leur contraste et leur meilleure encre selon WCAG 2.2 ; neuf matières et la matière que nomme un texte libre ; des
  clés de texture qui ne quittent jamais le dossier des textures.
- `SkinChoice` et `OwnedDie` : le choix d'un membre, et un dé d'un ensemble qu'il possède avec l'encre noire ou blanche
  qui ressort le plus sur sa couleur.
- `Contract\SkinCatalog`, `Contract\SkinChoiceStore` et `Contract\OwnedDice`, avec `Store\FixedSkinCatalog`,
  `Store\InMemorySkinChoiceStore` et `Store\NoOwnedDice` ; `Collections\ShelfOwnedDice`, les dés possédés lus sur
  l'étagère de `tilttek/collections`.
- `DiceBundle\DiceSkinsAppearance` : l'adaptateur du port `DiceAppearance` de `tilttek/dice-bundle` 0.2.0-alpha,
  l'apparence du dé d'un membre donnée à la personne qui le lance avec son nom, les couleurs de ses faces et de ses
  chiffres, sa matière et sa clé de texture, en contraste standard ou élevé selon ce que demande l'accessibilité de la
  personne qui regarde que passe la personne qui lance, ou selon la façon dont l'adaptateur a été construit sans elle ;
  aucune apparence pour un nombre de faces qu'aucun dé n'a. `tilttek/dice-bundle` est suggéré.
- Un conflit avec `tilttek/dice-bundle` antérieur à 0.2 : l'adaptateur utilise son API 0.2.
- `Exception\DiceSkinsException`, avec `InvalidSkin` et `SkinRefused`.
- Une cible de fuzzing pour les définitions d'habillages, et des bancs d'essai d'une apparence et d'un catalogue de 256
  habillages.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
