<!-- phil-dev source-sha256=6681c6cc4aa84023fdaf2de971c407d499f1ba58d66674ee8b1a6357caab44e4 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Grid\Exception\GridException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidGrid` | Une grille, une coordonnée ou un alignement est refusé : une taille qui n'est pas positive, des coordonnées cubiques dont la somme n'est pas nulle, un tableau mal formé, deux cellules de référence qui ne peuvent fixer une grille, un ratio de dénominateur nul | `Two reference cells differ in column and in row.` |
| `OutOfBounds` | Un calcul sortirait des bornes de la bibliothèque : une coordonnée trop éloignée, une ligne, une zone ou un champ de vision trop grand, un nombre trop grand pour l'arithmétique exacte | `The radius of an area lies from 0 to 100; it is 101.` |
