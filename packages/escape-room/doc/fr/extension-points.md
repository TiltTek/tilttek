<!-- phil-dev source-sha256=222cbc8b4f48a8cfff7c628efde8fa03b11748ce3d1dcbc4d5b04ce0e5800531 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Fourni |
|---|---|---|
| `Tilttek\PuzzleBuilder\PuzzleEngine` | Le moteur d'énigmes, avec son limiteur de réponses et ses propres points | — |
| `Contract\SessionReservations` | Les réservations des séances | `Store\InMemorySessionReservations`, `Reservation\BookableSessionReservations` |
| `Contract\SeatLedger` | Où `Reservation\BookableSessionReservations` garde les réservations | `Store\InMemorySeatLedger` |
| `Reservation\Contract\RoomBookables` | Le `Bookable` de chaque salle, avec ses heures d'ouverture et ses règles | aucun : un `Bookable` avec la seule capacité |
| `Psr\EventDispatcher\EventDispatcherInterface` | Où vont les événements | aucun : rien n'est envoyé |
| `Psr\Clock\ClockInterface` | La minuterie et le début d'une séance | `Symfony\Component\Clock\NativeClock` |

Chaque point est passé à un constructeur ; `EscapeRooms` et `Booking\SessionBooker` ne gardent rien entre deux appels.
Les vérifications des réponses et les limiteurs sont ceux de `tilttek/puzzle-builder`.

## Les événements

Chaque événement est envoyé une fois son coup joué, et nomme des identifiants — la salle, l'équipe, la séance, l'énigme,
l'animateur —, jamais une proposition ni une réponse. `Event\ScreenMessageSent` est le seul qui porte un texte : le
message que l'animateur a écrit pour l'écran d'indices.

| Événement | Porte |
|---|---|
| `Event\GameStarted` | La salle, l'équipe, la séance et le départ |
| `Event\GamePaused`, `Event\GameResumed` | La salle, l'équipe, la séance, l'animateur et l'heure |
| `Event\HintRequested` | La salle, l'équipe, la séance, l'énigme, le palier, la pénalité et l'heure |
| `Event\HintPushed` | La même chose, et l'animateur |
| `Event\PuzzleSkipped` | La salle, l'équipe, la séance, l'énigme, la pénalité, l'animateur et l'heure |
| `Event\ScreenMessageSent` | La salle, l'équipe, la séance, le message, l'animateur et l'heure |
| `Event\ScreenCleared` | La salle, l'équipe, la séance, l'animateur et l'heure |
| `Event\GameFinished` | La salle, l'équipe, la séance, l'issue, le temps final et l'heure de la fin |
| `Event\ConsentChanged` | La salle, l'équipe, la séance, et si le résultat est public |
| `Event\SeatsBooked` | La salle, la séance, la réservation, le groupe, les places, et si elle est privée |

## Ce qu'ajoute un bundle

La bibliothèque s'arrête à ses événements et à ses valeurs simples. Un bundle pour Symfony ajoute le reste :

- les parties, les résultats et les réservations stockés avec Doctrine ;
- l'écran d'indices en temps réel : chaque coup de la console est dit à l'écran par une mise à jour Mercure qui ne
  nomme que le coup, et l'écran lit l'indice, le message ou la minuterie depuis le serveur ;
- une tâche planifiée qui appelle `expire()` quand une minuterie atteint 0 ;
- la console de l'animateur, en contrôleur Stimulus ;
- la page du classement, et l'effaceur que `phil/privacy-bundle` appelle pour effacer un membre.

`tilttek/escape-room-bundle` est ce bundle.

## Accessoires et capteurs

La bibliothèque ne pilote ni serrure, ni lumière, ni capteur. Une application qui le fait écoute les événements —
`Event\PuzzleSolved` du moteur ouvre une porte, `Event\GameFinished` allume la sortie — et appelle l'API depuis ses
propres appareils : une serrure qui s'ouvre est un `guess()`, ou un `skip()` de l'animateur.
