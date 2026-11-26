<!-- phil-dev source-sha256=6dbdd1108ea0de1311124d930ba60ec0e527bf23e89bae66c659df253ac09634 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Tournaments

| Type | Rôle |
|---|---|
| `Tournaments` | Le service : démarre les tournois, prépare et publie les rondes, prend, tranche et corrige les résultats, prend les retraits et déplace les rondes ; donne le classement, le tableau, le calendrier d'un participant et l'explication d'un appariement. |
| `Tournament` | Un tournoi tel qu'il est déclaré : participants, étapes, pointage, bris d'égalité et graine publiée. |
| `TournamentState` | Un tournoi tel que le dépôt le garde : sa déclaration, le premier ordre, les rondes, les résultats, les conflits, les retraits, le podium et la version. |
| `Stage` | Une étape : suisse, toutes rondes ou élimination simple, avec ses rondes, poules, tours et qualifiés. |
| `StageFormat` | Le format d'une étape. |
| `Scoring` | Les points d'une victoire, d'un nul, d'une défaite, d'une exemption et des forfaits, en nombres entiers. |
| `Round` | Une ronde d'une étape : son numéro, ses tables et sa date de début. |
| `Pairing` | Une table : le participant qui joue en premier, celui qui joue second ou aucun pour une exemption ou un repos, la poule et les raisons. |
| `PairingReason` | Une raison d'un appariement : son genre, le participant, l'autre participant et la ronde à laquelle elle renvoie. |
| `ReasonKind` | Les genres de raisons, sous forme de codes stables. |
| `ScheduledGame` | Une ronde dans le calendrier d'un participant. |
| `Explanation` | Pourquoi deux participants se rencontrent dans une ronde, ou non. |
| `ExplanationKind` | Les genres d'explications, sous forme de codes stables. |
| `SeededDraw` | L'ordre que la graine publiée donne aux participants. |
| `Identifier` | Interne : la forme de chaque identifiant. |
| `Progress` | Interne : ce que l'état d'un tournoi donne, pour un appel. |

## Résultats

| Type | Rôle |
|---|---|
| `Result` | Le résultat d'un match : son issue et ses points de mission. |
| `Outcome` | Comment un match s'est terminé, du côté du premier joueur. |
| `Submission` | Un résultat tel qu'une personne l'a saisi. |
| `Recording` | Ce qu'un résultat saisi, tranché ou corrigé, ou un retrait, a fait : son statut, les changements du classement, les événements. |
| `RecordingStatus` | Enregistré, inchangé ou en conflit. |
| `Records` | Les dossiers des participants d'une étape. |
| `Record` | Ce qu'un participant a fait dans une étape. |
| `Game` | Un match d'un participant, tel que le classement le lit. |

## Appariements

| Type | Rôle |
|---|---|
| `Swiss\SwissPairer` | Apparie une ronde suisse par groupes de points. |
| `Contract\Matcher` | Interne : un jumelage du coût le plus bas trouvé. |
| `Swiss\ExactMatcher` | Interne : le jumelage du coût le plus bas, pour de petits groupes. |
| `Swiss\BoundedMatcher` | Interne : un jumelage de faible coût, en temps borné, pour de plus grands groupes. |
| `RoundRobin\BergerTable` | Les rondes d'une poule par les tables de Berger. |
| `RoundRobin\Pools` | Des participants répartis sur des poules en serpentin. |
| `Elimination\Bracket` | Un tableau à élimination simple par têtes de série. |
| `Elimination\BracketMatch` | Un match d'un tableau. |
| `Elimination\BracketState` | Un tableau rempli de ce qui est connu : champion, podium, les tables d'une ronde, la ronde atteinte par chaque participant. |
| `Elimination\MatchState` | Où en est un match d'un tableau. |
| `Elimination\SeedOrder` | Les places des têtes de série dans un tableau. |
| `Elimination\Slot` | Une place d'un tableau : un participant, un qualifié, le gagnant d'un match, ou une exemption. |
| `Elimination\SlotKind` | Ce qui remplit une place. |

## Classement et bris d'égalité

| Type | Rôle |
|---|---|
| `Standings\Standings` | Le classement d'une étape ou d'une poule, et ce qui a bougé depuis d'autres. |
| `Standings\Standing` | La place d'un participant : rang, points, scores des bris d'égalité, le tirage, le retrait. |
| `Standings\StandingChange` | Comment le rang et les points d'un participant ont bougé. |
| `Standings\StandingsCalculator` | Classe par points, puis par les bris d'égalité dans l'ordre, puis par le tirage. |
| `Contract\Tiebreaker` | Un bris d'égalité. |
| `Tiebreak\Tiebreakers` | Les bris d'égalité qu'un tournoi peut déclarer, par code. |
| `Tiebreak\Buchholz` | Le score Buchholz, tronqué ou non. |
| `Tiebreak\SonnebornBerger` | Le score Sonneborn-Berger. |
| `Tiebreak\HeadToHead` | Les points marqués entre les participants encore à égalité. |
| `Tiebreak\Wins` | Les matchs gagnés sur l'échiquier. |
| `Tiebreak\MissionPoints` | Les points de mission marqués. |

## Classement initial, stockage et événements

| Type | Rôle |
|---|---|
| `Contract\SeedingSource` | D'où viennent les forces des participants. |
| `Seeding\FixedSeeding` | Les forces données, ou une liste en ordre. |
| `Seeding\RatingSeeding` | Les forces des cotes de `tilttek/rating` pour un jeu. |
| `Seeding\InitialOrder` | Le premier ordre : par force, puis par la graine. |
| `Contract\TournamentStore` | Où les tournois sont gardés, à une version. |
| `Store\InMemoryTournamentStore` | Les tournois en mémoire, pour les tests. |
| `Event\TournamentFinished` | Le tournoi a son gagnant et son podium. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\TournamentException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidTournament` | Une valeur hors de son intervalle ou de sa forme, ou inconnue. |
| `Exception\InvalidResult` | Un résultat qui ne convient pas à sa table. |
| `Exception\RoundRefused` | Une ronde demandée trop tôt ou trop tard. |
| `Exception\TournamentConflict` | Le tournoi a changé depuis sa lecture. |
| `Exception\TournamentNotFound` | Aucun tournoi n'a l'identifiant. |
