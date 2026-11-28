<!-- phil-dev source-sha256=0da4b9c205ef50d5483e72700e68ad4db3bc99f8566ef1048e17b647ce9ee35a -->
# Grilles sur des images

Une grille sur l'image d'une carte se place par une origine et la taille d'une cellule, en pixels depuis le coin
supérieur gauche de l'image, x vers la droite et y vers le bas. Les positions sont des valeurs `Point` de fractions
exactes ; voyez [Arithmétique exacte](exact-arithmetic.md).

## Grilles carrées

`Square\SquareGrid` prend le coin supérieur gauche de la cellule (0, 0) et la largeur et la hauteur d'une cellule.

```php
$grid = new SquareGrid(Point::of(12, 8), Ratio::of(140), Ratio::of(140));
$grid = SquareGrid::ofPixelsPerCell(140);    // origin at the corner of the image

$grid->cellAt(Point::of(300, 451));          // the cell under the pointer
$grid->centerOf(new Cell(2, 3));             // its centre, exact
$grid->snapToCenter($drop);                  // where a token dropped there lands
$grid->snapToCorner($drop);                  // where a token as large as four cells lands
```

Une position sur un côté appartient à la cellule à sa droite ou en dessous.

## Grilles hexagonales

`Hex\HexGrid` prend l'`Orientation`, le centre de l'hexagone (0, 0), et la largeur et la hauteur d'un hexagone tel qu'il
est dessiné : d'un côté à l'autre et d'une pointe à l'autre. Un hexagone dessiné étiré est mesuré tel qu'il est dessiné,
de sorte que la grille s'y ajuste.

```php
$grid = new HexGrid(Orientation::Pointy, Point::of(70, 80), Ratio::of(121), Ratio::of(140));
$grid->cellAt(Point::of(640, 512));          // an Axial
$grid->centerOf(new Axial(3, -1));
$grid->snapToCenter($drop);
```

L'hexagone sous une position se trouve avec des entiers, à partir des fractions exactes de la position : un pointeur
tombe toujours dans le même hexagone.

## Grilles isométriques

`Isometric\IsometricGrid` prend le coin supérieur du losange (0, 0) et la largeur et la hauteur d'un losange. Ses
cellules sont celles d'une grille carrée — `x` vers le bas à droite, `y` vers le bas à gauche —, et ses distances et ses
lignes aussi.

## Aligner une carte numérisée

Une carte numérisée commence rarement au coin de son image et a rarement des cellules entières. Cliquez les centres de
deux cellules et donnez leurs coordonnées : la grille suit, origine et tailles, exactement.

```php
$grid = SquareGrid::aligned(Point::of(212, 180), new Cell(1, 1), Point::of(3010, 2290), new Cell(21, 16));
$grid = HexGrid::aligned(Orientation::Flat, $first, new Axial(0, 2), $second, new Axial(9, -1));
```

- Pour les carrés, les deux cellules diffèrent par la colonne et par la rangée.
- Pour les hexagones à pointe vers le haut, elles sont dans des rangées différentes et pas l'une directement au-dessus
  de l'autre ; pour ceux à côté plat vers le haut, dans des colonnes différentes et pas l'une directement à côté de
  l'autre.
- Plus elles sont éloignées, plus la grille est exacte.
- Une paire qui ne peut fixer les deux tailles, ou qui donne une taille non positive — une cellule à qui on a donné les
  coordonnées d'une autre —, est refusée avec `InvalidGrid`.

L'image doit être droite ; redressez une numérisation de travers avant de l'aligner.

## Cartes sans grille

`Gridless` mesure une carte sans grille dans ses propres unités, à partir des pixels que couvre une unité :

```php
$map = new Gridless(Ratio::of(14));                        // 14 pixels to a foot
$map->distance(Point::of(0, 0), Point::of(42, 56));        // 5
```

La distance est la distance en ligne droite, arrondie à une unité entière par le `\RoundingMode` donné, sans étape à
virgule flottante.

## Noms de fichier

Les auteurs de cartes écrivent souvent la grille d'une carte dans son nom de fichier. `MapHints::fromFileName()` la
lit :

| Écrit | Lu comme |
|---|---|
| `140ppi`, `140px`, `140pxpi`, `300dpi`, `150 DPI`, `150_dpi` | 140, 140, 140, 300, 150 et 150 pixels par cellule |
| `30x20`, `30 x 20`, `30×20` | 30 colonnes et 20 rangées |

```php
$hints = MapHints::fromFileName('maps/forest_30x20_140ppi.jpg');
$hints->pixelsPerCell;                         // 140
[$hints->columns, $hints->rows];               // [30, 20]
$hints->cellWidth(4200);                       // the pixels per cell, or the image width shared by the columns
```

- Une carte à imprimer à 300 DPI avec des carrés d'un pouce a 300 pixels par cellule : le DPI se lit donc comme des
  pixels par cellule.
- Les répertoires d'un chemin sont écartés.
- Les pixels par cellule sont lus de 10 à 2 000, les colonnes et les rangées de 1 à 999 ; une valeur hors bornes, un
  nombre collé à d'autres chiffres — `1920x1080` — et un nom de plus de 255 octets ne donnent aucun indice.
- Un nom qui ne dit rien donne des indices vides, jamais une erreur : `isEmpty()` le dit.

## Sortes de grille

`GridKind` nomme la grille d'une carte telle qu'une application la garde à côté de la carte : `square`, `hex-pointy`,
`hex-flat`, `isometric` ou `none`.
