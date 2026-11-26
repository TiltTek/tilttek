<!-- phil-dev source-sha256=17afd3abfca6e16c3d0e28a4692ad0f1d5a555e7b83e2ffb8b71a2bfa4ce9485 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\MiniGame` | Ses propres mini-jeux | [Écrire un mini-jeu](writing-a-mini-game.md) |
| `Contract\ScoreSink` | Où vont les scores des jeux quotidiens : ses classements, son journal d'activité | [Jeux quotidiens](daily-games.md#le-puits-de-scores) |
| `DailySeed` | Le secret de ses jeux quotidiens, tiré de sa configuration | [Jeux quotidiens](daily-games.md#la-graine-du-jour) |
| `CellSymbols` | Les symboles de ses résultats partagés | [Partager un résultat](sharing.md#écrire-une-grille) |
| Les magasins de `tilttek/game-engine` | Le magasin des journaux, le cache d'états et l'éditeur de `Tables` | [Installation](installation.md#câbler-la-bibliothèque) |
| La célébration | Ce que joue une victoire, dans son interface | [Fins, records et historique](endings-and-history.md#le-ton-dune-fin) |

`DailyGames`, `MiniGames` et les jeux d'exemple ne gardent aucun état entre deux appels.
