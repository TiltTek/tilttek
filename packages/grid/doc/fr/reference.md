<!-- phil-dev source-sha256=9cbab01988d67dd2b3c21672b57fb0e7f51fd3268c55c394f6087420781ae7e7 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Square\Cell` | Une cellule d'une grille carrée ou isométrique, ses voisines et ses rotations |
| `Square\DiagonalRule` | Comment compte un pas en diagonale : orthogonal, 5-5-5, 5-10-5, euclidien |
| `Square\Movement` | Distances, lignes, zones, anneaux et champs de vision sur des carrés, selon une règle |
| `Square\SquareGrid` | Des cellules carrées sur une image : cellule sous une position, centre, accrochage, alignement |
| `Isometric\IsometricGrid` | Des cellules en losange sur une image : cellule sous une position, centre, accrochage |
| `Hex\Axial` | Un hexagone en coordonnées axiales : distances, voisins, lignes, zones, anneaux, champs de vision, rotations |
| `Hex\Cube` | Un hexagone en coordonnées cubiques, et l'hexagone le plus proche d'une position fractionnaire |
| `Hex\Offset` | Un hexagone en coordonnées décalées, colonne et rangée |
| `Hex\OffsetLayout` | Quelles rangées ou colonnes sont décalées : `odd-r`, `even-r`, `odd-q`, `even-q` |
| `Hex\Orientation` | Des hexagones avec une pointe ou un côté plat vers le haut |
| `Hex\HexGrid` | Des hexagones sur une image : hexagone sous une position, centre, accrochage, alignement |
| `Gridless` | Des distances sur une carte sans grille, dans ses propres unités |
| `GridKind` | La grille d'une carte, telle qu'elle est gardée à côté d'elle |
| `MapHints` | Les pixels par cellule, les colonnes et les rangées lus dans un nom de fichier |
| `Point` | Une position sur une image, exacte |
| `Ratio` | Une fraction exacte, son arithmétique et son arrondi |
| `Limits` | Les bornes de chaque calcul |
| `Exception\GridException` et ses deux exceptions | [Exceptions](exceptions.md) |
