<!-- phil-dev source-sha256=da20c3ef4a187b6a5f8133dcb5607ad6f5a747a7b2ca2526e2bed703ce295185 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\PlayLogException` : attrapez-la pour les attraper toutes.

| Exception | Levée quand |
|---|---|
| `Exception\InvalidPlay` | Une valeur sort de sa forme ou de ses bornes : un identifiant, le lieu, un score, les minutes, les places, un gagnant hors d'une partie compétitive, une limite, une période qui finit avant de commencer. |
| `Exception\PlayRefused` | Le journal n'autorise pas le changement : une nouvelle partie n'est pas à la version 0 ou a l'identifiant d'une partie gardée, le membre n'a pas enregistré la partie qu'il change, une correction change qui l'a enregistrée, le membre n'a aucune place à confirmer ou à refuser, ou le jeu est caché dans le catalogue. |
| `Exception\PlayNotFound` | Aucune partie n'a cet identifiant. |
| `Exception\PlayConflict` | La partie a changé depuis sa lecture : lisez-la de nouveau et refaites le changement. |

Un jeu que le catalogue ne contient pas lève l'`EntryNotFound` de `tilttek/game-catalog`. Rien n'est gardé quand une
exception est levée.
