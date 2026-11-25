# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Shipped |
|---|---|---|
| `Application\Contract\EscapeRoomAccess` (`access`) | Who runs, watches and speaks for a game | `NoEscapeRoomAccess`: nobody |
| `Application\Contract\RoomCatalog` (`room_catalog`) | The rooms | `ConfiguredRoomCatalog`, from the configuration |
| `Application\Contract\TeamNames` (`team_names`) | The names of the teams on the leaderboard | `UnnamedTeams`: by their place |
| `Tilttek\EscapeRoom\Contract\SessionReservations` (`reservations`) | The reservations of the sessions | On `phil/reservation`, or in memory |
| `Tilttek\EscapeRoom\Reservation\Contract\RoomBookables` (`room_bookables`) | The opening hours and rules of each room | A `Bookable` with the capacity alone |
| `Tilttek\PuzzleBuilder\Contract\GuessLimiter` (`guess_limiter`) | The limit of the guesses | On Symfony Rate Limiter, or in memory |
| `Application\Contract\GameStore`, `ResultStore`, `ScreenStore` | Where the games, results and screens are kept | On Doctrine DBAL, or in memory |
| `Tilttek\EscapeRoom\Contract\SeatLedger`, `Application\Contract\PartyBookings` | Where the bookings are kept | `DbalSeatLedger`, `InMemorySeatLedger` |
| `Application\Contract\ScreenPublisher` | How the screens hear of the moves | `MercureScreenPublisher` |

A store is replaced by aliasing its contract to your own service; it passes the bundle's store scenarios
(`tests/Unit/Infrastructure/Store/StoresTestCase.php`) and keeps nothing between two requests of a worker, or resets it
on `kernel.reset`. For an erasure, `GameStore::forget()` and `ScreenStore::forget()` delete the rows of the games named,
`ResultStore::forgetTeam()` deletes the results of a team, `GameStore::eraseTeam()` writes the pseudonym in the games
left and withdraws their consent, and `ScreenStore::eraseHost()` and `PartyBookings::eraseParty()` write it in the
screens and bookings left; see [Privacy](privacy.md).

- **The events.** `EscapeRooms` and `SessionBooker` dispatch the library's events through the application's dispatcher:
  a listener on `GameFinished` lights the exit, one on `SeatsBooked` sends the confirmation.
- **The templates.** Override `@TilttekEscapeRoom/console.html.twig`, `screen.html.twig`, `_screen.html.twig` or
  `leaderboard.html.twig` under `templates/bundles/TilttekEscapeRoomBundle/`, or install other recipes of the same names.
- **The scripts.** Listen to `tilttek-escape-room:moved` and `tilttek-escape-room:finished` on the hint screen.
- **The team's screen.** The bundle draws no page for the players' guesses: an application's own page calls
  `GameDesk::guess()` and `requestHint()`, behind its own access, with the limit of the guesses.
