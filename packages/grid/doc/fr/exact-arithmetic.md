<!-- phil-dev source-sha256=a4af3e1959dcbfa1131d1780da282f390088570453d2b2bd7b4031c5b9caa06a -->
# Arithmétique exacte

## Ratio

`Ratio` est une fraction exacte de deux entiers, sous sa forme irréductible avec un dénominateur positif. Les tailles
et les positions sur une image sont des ratios : une carte numérisée de 4 213 pixels pour 30 colonnes a des cellules de
4213/30 pixels, et la cellule sous un pointeur ne dépend jamais de l'arrondi d'un nombre à virgule flottante.

```php
$width = Ratio::of(4213, 30);
$width->plus(Ratio::of(1, 2));        // also minus(), times(), dividedBy(), negated(), abs()
$width->compare(Ratio::of(140));      // -1, 0 or 1
$width->floor();                      // 140
$width->round();                      // 140
$width->toDecimal(2);                 // '140.43', for display only
(string) $width;                      // '4213/30'
```

Chaque opération vérifie que ses produits et ses sommes intermédiaires restent dans les entiers de PHP, et refuse avec
`OutOfBounds` plutôt que de perdre en précision.

## Arrondi

`Ratio::round()`, `Ratio::sqrtRounded()`, `Point::rounded()`, `DiagonalRule::cells()` et `Gridless` prennent le
`\RoundingMode` de PHP, `HalfAwayFromZero` par défaut :

| Mode | 2,5 | -2,5 | 2,4 |
|---|---|---|---|
| `HalfAwayFromZero` | 3 | -3 | 2 |
| `HalfTowardsZero` | 2 | -2 | 2 |
| `HalfEven` | 2 | -2 | 2 |
| `HalfOdd` | 3 | -3 | 2 |
| `TowardsZero` | 2 | -2 | 2 |
| `AwayFromZero` | 3 | -3 | 3 |
| `NegativeInfinity` | 2 | -3 | 2 |
| `PositiveInfinity` | 3 | -2 | 3 |

`sqrtRounded()` arrondit la racine carrée d'un ratio en comparant des carrés d'entiers : la distance euclidienne de 3
cellules sur 4 est 5, et celle de 1 sur 1 est 1 — ou 2 avec `PositiveInfinity` —, la même sur chaque machine.

## Bornes

| Borne | Constante | Valeur |
|---|---|---|
| Une coordonnée de cellule | `Limits::COORDINATE` | -1 000 000 à 1 000 000 |
| Les pas d'une ligne | `Limits::LINE` | 10 000 |
| Le rayon d'une zone ou d'un anneau | `Limits::RADIUS` | 100 |
| Le rayon d'un champ de vision | `Limits::SIGHT` | 30 |

Ces bornes gardent court le temps de chaque appel, quelle que soit l'entrée : une zone de rayon 100 compte 30 301
hexagones, et un champ de vision de rayon 30 trace 2 790 lignes.
