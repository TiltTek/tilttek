<!-- phil-dev source-sha256=45c697ff2c1fe0be6d578b5227b06ab535ea02ffee9d4812d6afb9df14db9ac8 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| Ce qui bloque la vue | Une fermeture qui dit si une cellule ou un hexagone bloque, lue dans sa propre carte | [Mesurer](measuring.md#champs-de-vision) |
| La règle de diagonale et l'arrondi | La règle de son jeu, et le `\RoundingMode` de ses distances | [Mesurer](measuring.md#carrés-et-règles-de-diagonale) |
| Le placement d'une grille | L'origine et les tailles qu'elle garde à côté d'une carte, ou les deux cellules qu'un utilisateur a cliquées | [Grilles sur des images](images.md) |

Chaque type est une valeur immuable ; la bibliothèque n'appelle que les fermetures qu'on lui donne et ne garde aucun
état entre deux appels.
