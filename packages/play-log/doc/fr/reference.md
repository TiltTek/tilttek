<!-- phil-dev source-sha256=44b339c526b6f6d4b0efc811b28557300d6c86d671e4e421fe5fd0ed3880298b -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Parties

| Type | Rôle |
|---|---|
| `PlayLog` | Le service : enregistrer, corriger, supprimer, confirmer, refuser, lire et oublier. |
| `Play` | Un jeu joué une fois : son jeu, le membre qui l'a enregistrée, ses places, son résultat, sa date, ses minutes, son groupe et son lieu. |
| `Seat` | Un membre ou un joueur anonyme, avec un score, s'il a gagné, et si le membre l'a confirmée. |
| `Result` | Compétitif, une victoire coopérative, une défaite coopérative, ou inachevée. |
| `PlayQuery` | Quelles parties lire : d'un membre, en attente d'un membre, enregistrées par un membre, ou d'un groupe, par jeu et par période. |
| `PlayQueryKind` | De qui une requête lit les parties. |

## Statistiques

| Type | Rôle |
|---|---|
| `PlayStatistics` | Le service : les statistiques d'un membre ou d'un groupe, calculées sur demande. |
| `Statistics` | Ce que totalisent les parties d'un membre ou d'un groupe. |
| `GameStatistics` | Ce que totalisent les parties d'un jeu. |
| `PlayerStatistics` | Un membre avec qui l'on a joué : ses parties et ses victoires. |

## Ports et stockages

| Type | Rôle |
|---|---|
| `Contract\PlayStore` | Garde les parties à une version, et les lit par requête. |
| `Contract\PlayerConsent` | Si un membre a accepté d'avance d'être lié aux parties d'un autre membre. |
| `Contract\PlayObserver` | Informé des événements d'une partie. |
| `Store\InMemoryPlayStore` | Les parties en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\PlayEvent` | Chaque événement : la partie et le jeu. |
| `Event\PlayRecorded` | Une partie a été enregistrée. |
| `Event\PlayCorrected` | Une partie a été corrigée. |
| `Event\PlayDeleted` | Une partie a été supprimée. |
| `Event\SeatLinked` | La place d'un membre compte pour lui. |
| `Event\SeatPending` | La place d'un membre attend sa confirmation. |
| `Event\SeatUnlinked` | La place d'un membre ne compte plus pour lui. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\PlayLogException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidPlay` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\PlayRefused` | Un changement que le journal n'autorise pas. |
| `Exception\PlayNotFound` | Aucune partie n'a cet identifiant. |
| `Exception\PlayConflict` | La partie a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme des identifiants. Interne. |
| `Internal\Text` | La forme du lieu. Interne. |
| `Internal\Tally` | Les décomptes d'un calcul de statistiques. Interne. |
