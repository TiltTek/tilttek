<!-- phil-dev source-sha256=5f0a4eb129e7f3fc6410722c5845a8b341737cd7f517ff82a6fe9676eaf55c7d -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Horloges

| Type | Rôle |
|---|---|
| `TurnClock` | Le service : créer, démarrer, passer, vérifier, mettre en pause, reprendre, vacances, ajuster, arrêter et voir. |
| `TableClock` | Une horloge : son contrôle du temps, ses joueurs dans l'ordre des places, le tour en cours et sa version. |
| `PlayerClock` | Le temps d'un joueur : budget, ce qui reste, tours, temps dépensé et vacances. |
| `TimeControl` | Le budget, le mode et l'incrément, et le temps supplémentaire. |
| `Mode` | Fischer, Bronstein, un délai, ou le budget entier par tour. |
| `Overtime` | Compté sous zéro, ou perdu. |
| `Vacation` | Une période durant laquelle l'horloge d'un joueur ne tourne pas. |
| `View\ClockView` | L'horloge à montrer à la table, et le moment où le tour s'épuise. |
| `View\PlayerTime` | Le temps d'un joueur à un moment. |

## Ports et stockages

| Type | Rôle |
|---|---|
| `Contract\ClockStore` | Garde les horloges à une version. |
| `Contract\ClockObserver` | Informé des événements d'une horloge. |
| `Store\InMemoryClockStore` | Les horloges en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\ClockEvent` | Chaque événement : l'horloge. |
| `Event\PlayerEvent` | Les événements d'un joueur : l'horloge et le joueur. |
| `Event\ClockStarted` | Une horloge a démarré. |
| `Event\TurnStarted` | Le tour d'un joueur a commencé. |
| `Event\TurnEnded` | Le tour d'un joueur a pris fin. |
| `Event\TimeExpired` | Le budget d'un joueur s'est épuisé. |
| `Event\ClockPaused` | Une horloge a été mise en pause. |
| `Event\ClockResumed` | Une horloge tourne de nouveau. |
| `Event\VacationDeclared` | Un joueur a déclaré des vacances. |
| `Event\TimeAdjusted` | Le temps d'un joueur a été changé. |
| `Event\ClockStopped` | Une horloge a été arrêtée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\TurnClockException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidClock` | Une valeur sort de sa forme ou de ses bornes. |
| `Exception\ClockRefused` | Un changement que l'horloge n'autorise pas. |
| `Exception\ClockNotFound` | Aucune horloge ni aucun joueur n'a l'identifiant. |
| `Exception\ClockConflict` | L'horloge a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant. |
| `Internal\Millis` | Des instants en millisecondes depuis l'époque. |
