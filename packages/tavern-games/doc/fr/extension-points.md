<!-- phil-dev source-sha256=ba178f0d520f5ca6f815343145cf57940f934904d622ae92897dbfd952a6830b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\TavernGame` | Ses propres jeux de taverne | [Écrire un jeu de taverne](writing-a-tavern-game.md) |
| `Contract\TurnTimer` | L'horloge de ses tours, qui rappelle les délais | [Tables et tours](tables-and-turns.md#le-minuteur-de-tour) |
| `Contract\Simulation` | Une position dont elle estime les chances | [Chances](odds.md#lestimateur) |
| Les magasins de `tilttek/game-engine` | Le magasin des journaux, le cache d'états et l'éditeur de `Tables` | [Installation](installation.md#câbler-la-bibliothèque) |
| La célébration | Ce que joue une victoire, dans son interface | [Tables et tours](tables-and-turns.md#fins) |

`TavernTables`, `TavernGames`, `DiceCup` et les jeux ne gardent aucun état entre deux appels.
