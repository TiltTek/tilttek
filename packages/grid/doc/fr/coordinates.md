<!-- phil-dev source-sha256=dd54d4421f081caffd8c731dc511fefc74ca7545523bd8394e844821a8468b70 -->
# Coordonnées

## Cellules carrées et isométriques

`Square\Cell` est une cellule d'une grille carrée, ou d'une grille isométrique, qui est une grille carrée dessinée en
losanges : sa colonne `x`, vers la droite, et sa rangée `y`, vers le bas. Elle s'écrit `{"x": 3, "y": 4}`, les clés des
cartes carrées de `tilttek/generator`.

```php
$cell = new Cell(3, 4);
$cell->toArray();                  // ['x' => 3, 'y' => 4]
Cell::fromArray(['x' => 3, 'y' => 4]);
$cell->key();                      // '3,4', to index cells in an array
```

## Hexagones

Un hexagone a trois systèmes de coordonnées, chacun avec son usage.

| Système | Type | S'écrit | Sert à |
|---|---|---|---|
| Axial | `Hex\Axial` | `{"q": 1, "r": 4}` | Chaque calcul de la bibliothèque : distances, lignes, zones, rotations |
| Cubique | `Hex\Cube` | `q`, `r`, `s`, avec `q + r + s = 0` | Voir une rotation ou une réflexion comme un échange d'axes |
| Décalé | `Hex\Offset` | `{"col": 3, "row": 4}` | Numéroter les hexagones d'une carte imprimée, colonne et rangée |

Les coordonnées axiales sont des coordonnées cubiques sans leur troisième axe, `s = -q - r` ; `Axial::s()` le donne, et
`Axial::toCube()` et `Cube::toAxial()` convertissent l'une en l'autre.

Les coordonnées décalées dépendent de quelles rangées ou colonnes sont décalées d'un demi-hexagone, le
`Hex\OffsetLayout` :

| Disposition | Hexagones | Décalées |
|---|---|---|
| `OddRows` (`odd-r`) | Pointe vers le haut, en rangées | Chaque rangée impaire, d'un demi-hexagone vers la droite |
| `EvenRows` (`even-r`) | Pointe vers le haut, en rangées | Chaque rangée paire, d'un demi-hexagone vers la droite |
| `OddColumns` (`odd-q`) | Côté plat vers le haut, en colonnes | Chaque colonne impaire, d'un demi-hexagone vers le bas |
| `EvenColumns` (`even-q`) | Côté plat vers le haut, en colonnes | Chaque colonne paire, d'un demi-hexagone vers le bas |

`OffsetLayout::orientation()` donne le `Hex\Orientation` d'une disposition, `Pointy` ou `Flat`.

```php
$axial = new Offset(3, 4)->toAxial(OffsetLayout::OddRows);   // q 1, r 4
$axial->toOffset(OffsetLayout::OddRows);                     // col 3, row 4
```

Les conversions valent aussi pour les rangées et les colonnes négatives, et vont et reviennent pour chaque coordonnée
dans les bornes.

## Les cartes hexagonales de tilttek/generator

Les cartes hexagonales de `tilttek/generator` sont des hexagones à pointe vers le haut, en rangées, chaque rangée
impaire décalée vers la droite : la disposition `OddRows`. Chacune de leurs cellules porte `col` et `row`, et `q` et
`r`, les clés que `Offset` et `Axial` lisent et écrivent, de sorte qu'une cellule d'une carte générée se convertit
directement :

```php
$hex = Axial::fromArray(['q' => $cell['q'], 'r' => $cell['r']]);
$hex->toOffset(OffsetLayout::OddRows)->toArray();           // ['col' => $cell['col'], 'row' => $cell['row']]
```

## Bornes

Chaque coordonnée — `x`, `y`, `q`, `r`, `s`, `col` et `row` — va de -1 000 000 à 1 000 000 ; une coordonnée au-delà est
refusée avec `OutOfBounds`. Un tableau mal formé est refusé avec `InvalidGrid`.
