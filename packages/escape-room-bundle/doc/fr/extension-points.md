<!-- phil-dev source-sha256=94c7cad4fcbe6bc972712bf4d28bf26adbffc79428976e28cbc9e25aa0d98f3b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Fourni |
|---|---|---|
| `Application\Contract\EscapeRoomAccess` (`access`) | Qui conduit, regarde et parle pour une partie | `NoEscapeRoomAccess` : personne |
| `Application\Contract\RoomCatalog` (`room_catalog`) | Les salles | `ConfiguredRoomCatalog`, depuis la configuration |
| `Application\Contract\TeamNames` (`team_names`) | Les noms des équipes dans le classement | `UnnamedTeams` : par leur place |
| `Tilttek\EscapeRoom\Contract\SessionReservations` (`reservations`) | Les réservations des séances | Sur `phil/reservation`, ou en mémoire |
| `Tilttek\EscapeRoom\Reservation\Contract\RoomBookables` (`room_bookables`) | Les heures d'ouverture et les règles de chaque salle | Un `Bookable` avec la seule capacité |
| `Tilttek\PuzzleBuilder\Contract\GuessLimiter` (`guess_limiter`) | La limite des propositions | Sur Symfony Rate Limiter, ou en mémoire |
| `Application\Contract\GameStore`, `ResultStore`, `ScreenStore` | Où sont gardés les parties, les résultats et les écrans | Sur Doctrine DBAL, ou en mémoire |
| `Tilttek\EscapeRoom\Contract\SeatLedger`, `Application\Contract\PartyBookings` | Où sont gardées les réservations | `DbalSeatLedger`, `InMemorySeatLedger` |
| `Application\Contract\ScreenPublisher` | Comment les écrans apprennent les coups | `MercureScreenPublisher` |

Un magasin se remplace en faisant pointer son contrat vers votre propre service ; il passe les scénarios de magasin du
bundle (`tests/Unit/Infrastructure/Store/StoresTestCase.php`) et ne garde rien entre deux requêtes d'un *worker*, ou se
remet à zéro sur `kernel.reset`. Pour un effacement, `GameStore::forget()` et `ScreenStore::forget()` suppriment les
lignes des parties nommées, `ResultStore::forgetTeam()` supprime les résultats d'une équipe, `GameStore::eraseTeam()`
écrit le pseudonyme dans les parties restantes et retire leur consentement, et `ScreenStore::eraseHost()` et
`PartyBookings::eraseParty()` l'écrivent dans les écrans et les réservations restants ; voyez
[Vie privée](privacy.md).

- **Les événements.** `EscapeRooms` et `SessionBooker` envoient les événements de la bibliothèque par le répartiteur de
  l'application : un écouteur de `GameFinished` allume la sortie, un de `SeatsBooked` envoie la confirmation.
- **Les gabarits.** Remplacez `@TilttekEscapeRoom/console.html.twig`, `screen.html.twig`, `_screen.html.twig` ou
  `leaderboard.html.twig` sous `templates/bundles/TilttekEscapeRoomBundle/`, ou installez d'autres recettes des mêmes
  noms.
- **Les scripts.** Écoutez `tilttek-escape-room:moved` et `tilttek-escape-room:finished` sur l'écran d'indices.
- **L'écran de l'équipe.** Le bundle ne dessine aucune page pour les propositions des joueurs : une page propre à
  l'application appelle `GameDesk::guess()` et `requestHint()`, derrière son propre accès, avec la limite des
  propositions.
