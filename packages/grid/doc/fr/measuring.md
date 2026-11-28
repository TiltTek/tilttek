<!-- phil-dev source-sha256=05b5380a53ebfc7bef14e1917dace6ec7b938326cb178e87a94bcb45bf09b518 -->
# Mesurer

## Hexagones

Sur des hexagones, chaque pas vers un voisin compte un.

| Méthode de `Hex\Axial` | Donne |
|---|---|
| `distanceTo($other)` | Le nombre de pas entre deux hexagones |
| `neighbor($direction)` | Le voisin dans une direction, de 0 à 5 ; tout autre nombre fait le tour des six |
| `neighbors()` | Les six voisins, dans l'ordre des directions |
| `diagonals()` | Les six hexagones à deux pas, de l'autre côté d'un coin |
| `lineTo($other)` | Les hexagones que traverse une ligne droite entre les deux centres, les deux extrémités comprises |
| `area($radius)` | Chaque hexagone à un rayon au plus : la zone d'un effet décrit par sa distance en hexagones |
| `ring($radius)` | Chaque hexagone à exactement un rayon |
| `fieldOfView($radius, $blocksSight)` | Les hexagones d'une zone qu'un observateur placé ici peut voir |
| `rotateAround($pivot, $sixthTurns)` | Cet hexagone tourné autour d'un pivot, dans le sens horaire tel qu'on voit la carte |
| `plus()`, `minus()`, `scale()` | Les hexagones comme vecteurs |

Les directions se comptent dans le sens antihoraire : pour les hexagones à pointe vers le haut, 0 est à l'est, 1 au
nord-est, 2 au nord-ouest, 3 à l'ouest, 4 au sud-ouest et 5 au sud-est ; pour ceux à côté plat vers le haut, 0 est au
sud-est, 1 au nord-est, 2 au nord, 3 au nord-ouest, 4 au sud-ouest et 5 au sud.

Une ligne entre deux hexagones passe exactement entre deux autres quand ils sont alignés de l'autre côté d'un coin ;
elle prend alors le même côté chaque fois, de sorte qu'une ligne, un champ de vision et leurs tests sont reproductibles.

## Carrés et règles de diagonale

Sur une grille carrée, la façon dont compte un pas en diagonale est une règle du jeu, `Square\DiagonalRule` :

| Règle | Valeur | Un déplacement de 3 colonnes et 2 rangées coûte |
|---|---|---|
| `Orthogonal` | `orthogonal` | 5 cellules : aucun pas en diagonale |
| `Uniform` | `5-5-5` | 3 cellules : un pas en diagonale coûte un |
| `Alternating` | `5-10-5` | 4 cellules : les pas en diagonale coûtent un, puis deux, tour à tour |
| `Euclidean` | `euclidean` | 4 cellules : la distance en ligne droite, 3,6, arrondie |

`DiagonalRule::cells($dx, $dy)` donne le coût d'un déplacement ; la règle euclidienne arrondit selon le
`\RoundingMode` donné, `HalfAwayFromZero` par défaut, calculé exactement. Multipliez par la taille d'une cellule — 5
pieds, 1,5 mètre — pour la distance dont parle le jeu.

`Square\Movement` applique une règle à des cellules :

```php
$movement = new Movement(DiagonalRule::Alternating);
$movement->distance(new Cell(0, 0), new Cell(3, 2));   // 4
$movement->line($from, $to);                           // each cell touches the one before by a side or a corner
$movement->area($center, 6);                           // every cell at most 6 away under the rule
$movement->ring($center, 6);                           // every cell exactly 6 away
$movement->fieldOfView($viewer, 12, fn (Cell $cell): bool => $walls->has($cell));
```

`Square\Cell` a `neighbors()` — les quatre qui partagent un côté, puis les quatre qui partagent un coin sauf avec
`neighbors(false)` — et `rotateAround($pivot, $quarterTurns)`.

## Champs de vision

Un champ de vision est chaque cellule d'une zone qu'un observateur placé en son centre peut voir : une cellule est vue
quand aucune cellule strictement entre elle et l'observateur, sur la ligne entre leurs centres, ne bloque la vue. Une
cellule qui bloque est elle-même vue : on voit un mur, pas ce qui se trouve derrière. La fermeture donnée dit si une
cellule bloque ; elle est appelée une fois par cellule, quel que soit le nombre de lignes qui la croisent.

L'observateur vient en premier dans le résultat, puis les cellules dans l'ordre de la zone.

## Tourner un morceau de carte

Tourner chaque cellule d'un morceau de carte — une pièce d'un donjon généré, une tuile d'une carte — autour de sa
cellule de référence fait tourner le morceau : un quart de tour pour les carrés, un sixième pour les hexagones, dans le
sens horaire tel qu'on voit la carte, antihoraire pour un nombre négatif. Les rotations conservent chaque distance au
pivot.

```php
$turned = array_map(fn (Axial $hex): Axial => $hex->rotateAround($reference, 2), $room);
```

## Bornes

| Calcul | Borne |
|---|---|
| Une ligne | 10 000 pas |
| Une zone ou un anneau | Un rayon de 100 |
| Un champ de vision | Un rayon de 30 |

Une demande au-delà est refusée avec `OutOfBounds` ; voyez [Arithmétique exacte](exact-arithmetic.md#bornes).
