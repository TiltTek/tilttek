<!-- phil-dev source-sha256=342c01415781d115eedd0128cac66ea5f0131e06a3f1f2dddba41a86c54320da -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/escape-room` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Room` : un scénario de `tilttek/puzzle-builder`, une durée de 60 à 86 400 secondes, les pénalités d'un indice et d'un
  saut jusqu'à une heure, des équipes de 1 à 100 joueurs ; `Room::chain()` pour des énigmes ouvertes l'une après
  l'autre, la dernière étant la finale.
- `EscapeRooms` : le compte à rebours lancé pour une équipe nommée par un identifiant stable ; les propositions, les
  indices demandés ou poussés par l'animateur, les sauts et les énigmes ouvertes, refusés pendant une pause, hors du
  temps ou une fois la partie finie ; l'évasion sur la finale ; les pauses et les reprises ; les messages et
  l'effacement de l'écran des indices ; l'expiration une fois le temps écoulé, au moment où le compte à rebours a
  atteint 0 ; l'abandon ; le consentement au classement public ; le résultat d'une équipe qui s'est évadée.
- `Game`, `GameOutcome` et `Timer` : l'état d'une partie gardé par l'application, écrit en valeurs simples en UTC et
  relu en le vérifiant contre la salle ; le temps joué dans la durée, pauses exclues, le temps qui reste, et le temps
  final avec les pénalités des indices et des sauts, jamais tiré du compte à rebours.
- `RoomGuess`, `RoomHint`, `RoomResult`, `Standing` et `Leaderboard` : le classement public d'une salle, sur
  consentement seulement, par temps final, indices et évasion, les égalités partageant une position, de 1 à 1 000
  places.
- `Booking\Session`, `Booking\SessionMode`, `Booking\SeatBooking`, `Booking\SessionBooker` et `Booking\BookingRefusal` :
  des séances privées pour un seul groupe et des séances partagées par places, les règles de la salle vérifiées avant
  que les réservations retiennent la réservation, chaque refus avec sa raison, ceux des réservations compris — fermé,
  trop tôt, trop loin à l'avance, hors horaire ; un groupe nommé par une adresse courriel refusé.
- `Contract\SessionReservations`, dont `reserve()` donne le refus des réservations ou null, avec
  `Store\InMemorySessionReservations`.
- `Reservation\BookableSessionReservations` : les réservations sur `phil/reservation` (suggéré), une salle étant un
  `Bookable` dont la capacité est sa plus grande équipe et une séance un intervalle de celui-ci, une réservation privée
  prenant toute la capacité, les violations du `Bookable` dites comme des refus ; `Reservation\Contract\RoomBookables`
  pour les heures d'ouverture et les règles de chaque salle ; `Contract\SeatLedger`, qui décide et écrit sous un seul
  verrou par séance, avec `Store\InMemorySeatLedger`.
- `EscapeRooms::ERASED_KIND` : une équipe, un groupe ou un animateur du type `erased` donné par l'appelant refusé avec
  `InvalidRoom::erased()` ; seul un effacement en écrit un.
- `Event\GameStarted`, `Event\GamePaused`, `Event\GameResumed`, `Event\HintRequested`, `Event\HintPushed`,
  `Event\PuzzleSkipped`, `Event\ScreenMessageSent`, `Event\ScreenCleared`, `Event\GameFinished`, `Event\ConsentChanged`
  et `Event\SeatsBooked`, distribués par PSR-14.
- `Exception\EscapeRoomException`, avec `InvalidRoom`, `GameRefused` et `BookingRefused`.
- Une cible de fuzzing pour les parties enregistrées, et des bancs d'essai d'une partie de 50 énigmes et du classement
  de 10 000 résultats.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
