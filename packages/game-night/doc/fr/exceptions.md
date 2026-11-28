<!-- phil-dev source-sha256=41d2637aec97895ef28cb20a138f8ba9745677629169941d743740eddfa6a689 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\GameNight\Exception\GameNightException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidNight` | Une valeur de soirée, de table, d'inscription, de vote, de choix ou de partie sort de sa forme ou de ses bornes | `A table has 1 to 30 seats.` |
| `NightRefused` | La soirée n'accepte pas le changement maintenant : annulée, non publiée, commencée ou complète, membre non inscrit, table sans sièges libres | `The night has started.` |
| `NightConflict` | La soirée a changé, ou a été ajoutée, depuis sa lecture : rien n'est conservé | `The game night night-42 changed since it was read.` |
| `NightNotFound` | Aucune soirée n'a cet identifiant | `No game night has the identifier night-9.` |

`GameNights::propose()` et `NightPlays::record()` laissent aussi passer l'`EntryNotFound` de `tilttek/game-catalog`,
quand un jeu n'est pas dans le catalogue.
