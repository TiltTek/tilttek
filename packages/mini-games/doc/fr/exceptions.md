<!-- phil-dev source-sha256=adbfa24186bc4f4a70430069e282293a85d45c9f27438913d9dd9d30421c6d69 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\MiniGames\Exception\MiniGameException`. Les exceptions de
`tilttek/game-engine` — `IllegalMove`, `ConcurrentMove`, `InvalidGame`, `UnknownTable`, `GameInProgress` — passent
telles quelles.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidMiniGame` | Une valeur est refusée : un jour, un secret trop court, une commande, une grille ou ses symboles, un code de Taureaux et Vaches, un jeu inconnu, deux jeux d'un même nom, un historique de plusieurs joueurs | `No mini-game is named "chess".` |
| `SeedNotRevealable` | Le journal d'un jeu quotidien, graine comprise, est demandé alors que sa journée n'est pas terminée | `The seed of the daily game "bulls-and-cows" of 2026-11-24 is shown once the day is over.` |

Un essai qu'un jeu d'exemple refuse — `{guess: "0447"}` — est une `IllegalMove` du moteur, avec le message du refus :
`A guess is 4 different digits.`
