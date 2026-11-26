<!-- phil-dev source-sha256=cf11165227a86502f1d60acde93182b90ef3ccec48982f231fa170ba3dd1ed27 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Tournament\Exception\TournamentException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidTournament` | Une valeur sort de son intervalle ou de sa forme : un identifiant, une graine, les participants, une étape, le pointage, les bris d'égalité, une ronde ou une table ; ou une étape, une poule, une ronde ou un participant est inconnu | `A Swiss stage has 1 to 64 rounds.` |
| `InvalidResult` | Un résultat ne convient pas à sa table : une table inconnue, une exemption, un nul dans une élimination, des points de mission hors intervalle, aucun conflit à trancher, aucun résultat à corriger | `A bye has no result to enter.` |
| `RoundRefused` | Une ronde est demandée trop tôt ou trop tard, ou une correction laisserait un tableau faux | `The round 2 of the stage swiss has results missing or conflicts to settle.` |
| `TournamentConflict` | Le tournoi a changé, ou a été ajouté, depuis sa lecture : rien n'est gardé | `The tournament cup changed since it was read.` |
| `TournamentNotFound` | Aucun tournoi n'a l'identifiant dans le dépôt | `No tournament has the identifier cup.` |
