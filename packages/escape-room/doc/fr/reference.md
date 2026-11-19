<!-- phil-dev source-sha256=31cc4bb97f471881884819a33b0918cf40581fc8166d08cad6a2eca807667027 -->
# Référence

| Type | Rôle |
|---|---|
| `Room` | Une salle : son scénario, sa durée, ses pénalités et la taille de ses équipes ; des salles enchaînées |
| `EscapeRooms` | Les coups d'une partie, la console de l'animateur, la minuterie, le consentement et les résultats |
| `Game` | L'état de la partie d'une équipe, écrit et relu en valeurs simples |
| `GameOutcome` | `Escaped`, `TimeUp` ou `Abandoned` |
| `Timer` | La minuterie, le temps joué, les pénalités et le temps final |
| `RoomGuess` | Ce qu'a donné une proposition ou un saut, et la partie après lui |
| `RoomHint` | L'indice révélé, sa pénalité, et la partie après lui |
| `RoomResult` | Le résultat d'une équipe qui s'est échappée |
| `Leaderboard` | Le classement public d'une salle, par consentement |
| `Standing` | Une place du classement |
| `Booking\Session` | Une séance d'une salle : ses heures et la façon dont ses places se vendent |
| `Booking\SessionMode` | `Private`, `Shared` ou `Either` |
| `Booking\SeatBooking` | Une réservation d'une séance par un groupe |
| `Booking\SessionBooker` | Réserve les séances selon les règles de la salle, et donne les places restantes |
| `Booking\BookingRefusal` | Pourquoi une réservation est refusée |
| `Contract\SessionReservations` | Les réservations des séances de l'application |
| `Store\InMemorySessionReservations` | Les réservations en mémoire |
| `Reservation\BookableSessionReservations` | Les réservations sur `phil/reservation`, gardées par un `Contract\SeatLedger` |
| `Reservation\Contract\RoomBookables` | Le `Bookable` de `phil/reservation` qui représente une salle, avec ses règles |
| `Contract\SeatLedger` | Où l'adaptateur garde les réservations, en décidant et en écrivant sous un seul verrou par séance |
| `Store\InMemorySeatLedger` | Les réservations de l'adaptateur en mémoire |
| `Event\GameStarted` | Une partie commencée |
| `Event\GamePaused` | Une partie mise en pause par l'animateur |
| `Event\GameResumed` | Une partie relancée par l'animateur |
| `Event\HintRequested` | Un indice demandé par les joueurs |
| `Event\HintPushed` | Un indice envoyé par l'animateur à l'écran d'indices |
| `Event\PuzzleSkipped` | Une énigme sautée par l'animateur |
| `Event\ScreenMessageSent` | Un message de l'animateur pour l'écran d'indices |
| `Event\ScreenCleared` | L'écran d'indices effacé |
| `Event\GameFinished` | Une partie terminée, avec son issue et son temps final |
| `Event\ConsentChanged` | Une équipe qui consent, ou ne consent plus, à montrer son résultat |
| `Event\SeatsBooked` | Une séance réservée |

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Exception\EscapeRoomException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidRoom` | Une salle, une séance, une réservation, un message, une limite de classement ou une partie stockée que la bibliothèque refuse ; une équipe ou un groupe nommé par une adresse courriel ; une équipe, un groupe ou un animateur du genre `erased` ; un `Bookable` qui ne détient pas la capacité de sa salle |
| `Exception\GameRefused` | Un coup que la partie n'autorise pas : terminée, en pause, hors du temps, temps non écoulé, autre salle, aucun résultat |
| `Exception\BookingRefused` | Une réservation refusée, avec son `Booking\BookingRefusal` |

Les exceptions de `tilttek/puzzle-builder` — une énigme verrouillée ou résolue, une proposition mal formée — passent
sans changement. Rien ne change quand une exception est lancée.
