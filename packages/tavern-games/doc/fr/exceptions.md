<!-- phil-dev source-sha256=c4e0cd6838e00c4d8a7a6f3cecd877ebb2a427e622571e15f0055ecc79556bd3 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\TavernGames\Exception\TavernGamesException`. Les exceptions de
`tilttek/game-engine` — `IllegalMove`, `ConcurrentMove`, `InvalidGame`, `UnknownTable` — passent telles quelles.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidTavernGame` | Une valeur est refusée : un jeu inconnu, deux jeux d'un même nom, un lancer sans dé ou de plus de 10, une estimation sans échantillon ou de plus de 100 000 | `No tavern game is named "chess".` |

Un coup qu'un jeu refuse est une `IllegalMove` du moteur, avec le message du refus :
`An eight names the suit to follow: {"suit": "S", "H", "D" or "C"}.` Des données qu'un jeu ne peut pas relire — un cache
qui rend autre chose — sont une `InvalidGame` : `The gestures of Crazy Eights are a list.`
