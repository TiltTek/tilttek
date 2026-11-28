<!-- phil-dev source-sha256=e1b3c4bbe9e929d8f22c9224e69d475d2afd5a39b21d787325ce57431b77c51c -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/grid` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Ratio` et `Point` : des fractions exactes réduites, leur arithmétique protégée contre le dépassement, leur arrondi
  selon chaque `\RoundingMode` et leur racine carrée arrondie sans étape en virgule flottante ; des positions sur une
  image.
- `Limits` : des coordonnées de -1 000 000 à 1 000 000, des lignes d'au plus 10 000 pas, des zones et des anneaux de
  rayon 100 au plus, des champs de vision de rayon 30 au plus.
- `Square\Cell` : les cases des grilles carrées et isométriques, écrites `x` et `y`, leurs quatre ou huit voisines et
  leurs quarts de tour autour d'un pivot.
- `Square\DiagonalRule` et `Square\Movement` : des déplacements orthogonaux, 5-5-5, 5-10-5 et euclidiens, et des
  distances, des lignes, des zones, des anneaux et des champs de vision selon une règle.
- `Hex\Axial`, `Hex\Cube`, `Hex\Offset`, `Hex\OffsetLayout` et `Hex\Orientation` : les coordonnées axiales, cubiques et
  décalées des hexagones, leurs conversions selon les quatre dispositions décalées, écrites avec les clés des cartes
  hexagonales de `tilttek/generator` ; des distances, des voisins, des diagonales, des lignes, des zones, des anneaux,
  des champs de vision et des sixièmes de tour autour d'un pivot, calculés avec des entiers.
- `Square\SquareGrid`, `Hex\HexGrid` et `Isometric\IsometricGrid` : des cases sur l'image d'une carte, la case sous une
  position, le centre d'une case, l'accrochage à un centre ou à un coin, et l'alignement d'une carte numérisée à partir
  de deux cases cliquées dessus.
- `Gridless` et `GridKind` : des distances dans les unités d'une carte sans grille, et les sortes de grilles.
- `MapHints` : les pixels par case, les colonnes et les rangées lus dans le nom de fichier de l'image d'une carte.
- Des exceptions sous une seule interface `GridException` : `InvalidGrid` et `OutOfBounds`.
- Des tests de propriétés sur des coordonnées aléatoires, une cible de fuzzing des noms de fichiers et un banc d'essai
  des chemins critiques.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
