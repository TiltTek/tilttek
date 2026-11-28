<!-- phil-dev source-sha256=2737d9a0900118111252826162f087fea7d39d98f39f9882a9929bf9a03fce62 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Soirées

| Type | Rôle |
|---|---|
| `GameNights` | Le service : planification, tables, publication, inscriptions, retraits, sièges, vote et annulation. |
| `GameNight` | Une soirée : quand et où, son hôte, ses tables, ses inscriptions, sa liste d'attente, ses jeux proposés et ses votes, et sa version. |
| `NightStatus` | Brouillon, publiée ou annulée. |
| `Table` | Une table : ses sièges, son jeu et le membre qui l'anime. |
| `Registration` | Un membre inscrit, avec un invité ou seul, confirmé ou en attente, et sa table. |
| `RegistrationStatus` | Confirmée ou en attente. |
| `NightPlays` | Le service qui envoie les jeux joués au journal des parties. |
| `PlayedGame` | Un jeu joué à une table d'une soirée. |

## Choisir des jeux

| Type | Rôle |
|---|---|
| `Choice\GameChooser` | Suggère des jeux selon les joueurs et les minutes. |
| `Choice\Criteria` | Ce pour quoi un jeu est choisi. |
| `Choice\Suggestion` | Un jeu suggéré, avec les votes sur lesquels il repose. |
| `Choice\OwnedGame` | Un jeu qu'un membre peut apporter. |

## Le vote

| Type | Rôle |
|---|---|
| `Vote\VoteResult` | Le vote compté et expliqué. |
| `Vote\Tally` | Les votes d'un jeu et sa part. |
| `Vote\VoteOutcome` | Aucun vote, un gagnant ou une égalité. |

## Ports et entrepôts

| Type | Rôle |
|---|---|
| `Contract\NightStore` | Conserve les soirées à une version. |
| `Contract\NightObserver` | Informé des événements d'une soirée. |
| `Contract\ShelfReader` | Lit les jeux que les membres peuvent apporter. |
| `Contract\PlayJournal` | Reçoit les jeux joués. |
| `Store\InMemoryNightStore` | Les soirées en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\NightEvent` | Chaque événement : l'identifiant de la soirée. |
| `Event\NightPublished` | La soirée s'est ouverte aux inscriptions. |
| `Event\NightFull` | Le dernier siège libre a été pris. |
| `Event\NightCancelled` | La soirée a été annulée. |
| `Event\SeatOffered` | Un membre en attente détient ses places. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\GameNightException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidNight` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\NightRefused` | Un changement que la soirée n'accepte pas maintenant. |
| `Exception\NightConflict` | La soirée a changé depuis sa lecture. |
| `Exception\NightNotFound` | Aucune soirée n'a cet identifiant. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme des identifiants. Interne. |
