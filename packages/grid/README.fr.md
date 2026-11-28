<!-- phil-dev source-sha256=d700a0e582266e96b9341558638c1ee4e645a93cd4f23f6f678371b3002aee95 -->
# tilttek/grid

**Mesurez, accrochez et alignez des cartes de jeu avec exactitude : grilles carrées, hexagonales et isométriques, avec coordonnées, distances, lignes de vue et zones d'effet, sans nombres à virgule flottante.**

tilttek/grid contient l'arithmétique des grilles dessinées sur les cartes de jeu : carrées, hexagonales avec la pointe
ou le côté plat vers le haut, isométriques, ou absentes. Il donne aux hexagones leurs trois systèmes de coordonnées —
décalées, comme les numérote une carte imprimée, axiales et cubiques — et convertit de l'un à l'autre. Il mesure les
distances, trouve les voisins, trace des lignes, des anneaux et des zones d'effet décrites par leur distance en
hexagones, calcule ce qu'un jeton voit au-delà des murs, et fait tourner un morceau de carte autour de sa cellule de
référence. Sur une grille carrée, la diagonale est une règle du jeu : 5-5-5, 5-10-5, euclidienne, ou aucune diagonale.

Sur l'image d'une carte, il trouve la cellule sous un pointeur, le centre d'une cellule, et accroche un jeton à un
centre ou à un coin. Une carte numérisée est alignée à partir de deux cellules cliquées dessus ; les pixels par cellule
et la taille de la carte sont lus dans le nom du fichier quand l'auteur de la carte les y a écrits. Chaque position est
une fraction exacte et chaque résultat un nombre entier : aucun nombre à virgule flottante ne décide dans quelle
cellule tombe un point ni de combien un jeton se déplace.

Il sert aux outils de cartes des jeux joués en ligne ou à la table — une exploration hexagonale, une carte révélée à
mesure qu'on l'explore, un donjon généré, un créateur de jetons —, jamais une table virtuelle. C'est une bibliothèque
PHP simple, sans dépendances.

## État

| Partie | État |
|---|---|
| `Hex\Axial`, `Hex\Cube`, `Hex\Offset`, `Hex\OffsetLayout` : les trois systèmes de coordonnées des hexagones et leurs conversions | Implémenté et testé |
| Distances, voisins, lignes, anneaux, zones et champs de vision sur les hexagones et les carrés | Implémenté et testé |
| `Square\DiagonalRule`, `Square\Movement` : déplacements 5-5-5, 5-10-5, euclidiens et orthogonaux | Implémenté et testé |
| `Square\SquareGrid`, `Hex\HexGrid`, `Isometric\IsometricGrid`, `Gridless` : cellules sur l'image d'une carte, accrochage et alignement | Implémenté et testé |
| `MapHints` : les pixels par cellule, les colonnes et les rangées lus dans un nom de fichier | Implémenté et testé |
| `Ratio`, `Point` : positions exactes et leur arrondi | Implémenté et testé |
| Un bundle Symfony, des types de formulaire et des aides Twig | Non commencé : un futur bundle |
| Trouver seul la grille ou les pièces d'une image | Non prévu ici : un paquet à venir |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Coordonnées](doc/fr/coordinates.md) | Les cellules carrées, les coordonnées axiales, cubiques et décalées des hexagones, et leurs conversions. |
| [Mesurer](doc/fr/measuring.md) | Distances, règles de diagonale, voisins, lignes, anneaux, zones, champs de vision et rotations. |
| [Grilles sur des images](doc/fr/images.md) | Cellules sous un pointeur, centres, accrochage, alignement d'une carte numérisée, cartes sans grille et noms de fichier. |
| [Arithmétique exacte](doc/fr/exact-arithmetic.md) | `Ratio`, les modes d'arrondi et les bornes de chaque calcul. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `GridException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\HexGrid;
use Tilttek\Grid\Hex\Offset;
use Tilttek\Grid\Hex\OffsetLayout;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\MapHints;
use Tilttek\Grid\Point;

// The hex 0304 of a printed map, odd rows pushed right.
$camp = new Offset(3, 4)->toAxial(OffsetLayout::OddRows);
$ruins = new Axial(7, 1);

$camp->distanceTo($ruins);                 // 6
$camp->lineTo($ruins);                     // the 7 hexagons from the camp to the ruins
$camp->area(2);                            // the 19 hexagons of a fireball of radius 2
$camp->fieldOfView(6, fn (Axial $hex): bool => $walls->has($hex)); // what a sentry sees

// Align a scanned map from two hexagons clicked on it, then find the hexagon under the pointer.
$grid = HexGrid::aligned(Orientation::Pointy, Point::of(212, 180), new Axial(1, 1), Point::of(1460, 905), new Axial(12, 6));
$grid->cellAt(Point::of(640, 512));

MapHints::fromFileName('forest_30x20_140ppi.jpg')->pixelsPerCell; // 140
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
