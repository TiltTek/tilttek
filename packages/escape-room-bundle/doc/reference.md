# Reference

## Classes

| Class | Role |
|---|---|
| `TilttekEscapeRoomBundle` | The bundle and its configuration `tilttek_escape_room` |
| `Application\GameDesk` | The moves of the games by their identifier |
| `Application\StoredGame` | A game: its identifier, room and state |
| `Application\Screen`, `Application\ScreenKind` | What the hint screen shows |
| `Application\EscapeRoomTopics` | The Mercure topic of a game |
| `Application\ConfiguredRoomCatalog` | The rooms of the configuration |
| `Application\NoEscapeRoomAccess` | The access that refuses every viewer |
| `Application\UnnamedTeams` | The names of no team |
| `Application\Contract\*` | `RoomCatalog`, `EscapeRoomAccess`, `TeamNames`, `GameStore`, `ResultStore`, `ScreenStore`, `PartyBookings`, `ScreenPublisher` |
| `Infrastructure\Doctrine\EscapeRoomTables`, `EscapeRoomSchemaListener` | The five tables, added to the generated schema |
| `Infrastructure\Doctrine\DbalGameStore`, `DbalResultStore`, `DbalScreenStore`, `DbalSeatLedger` | The stores on Doctrine DBAL |
| `Infrastructure\Memory\InMemoryGameStore`, `InMemoryResultStore`, `InMemoryScreenStore`, `InMemorySeatLedger` | The stores in memory |
| `Infrastructure\Scheduler\EscapeRoomSchedule`, `ExpireDue`, `ExpireDueHandler` | The schedule of the expiries |
| `Infrastructure\Console\ExpireCommand` | `tilttek:escape-room:expire` |
| `Infrastructure\Mercure\MercureScreenPublisher`, `ScreenSubscriptions` | The moves on Mercure, and the viewers' subscriptions |
| `Infrastructure\Http\ConsoleController`, `ScreenController`, `ConsentController`, `LeaderboardController` | The pages |
| `Infrastructure\Http\Screens`, `Views` | What the pages share, and what the templates read |
| `Infrastructure\Twig\EscapeRoomExtension`, `KitComponents` | The Twig functions and the names of the kit's recipes |
| `Infrastructure\Privacy\EscapeRoomPrivacy` | The export and erasure of a member |
| `Infrastructure\DependencyInjection\EscapeRoomServices`, `MercureHubPass`, `KitSubdirectoryPass` | The services and their wiring |

## Services

| Service | What it is |
|---|---|
| `Tilttek\EscapeRoomBundle\Application\GameDesk` | The desk, public |
| `Tilttek\EscapeRoom\EscapeRooms` | The library's games, telling the dispatcher |
| `Tilttek\EscapeRoom\Booking\SessionBooker` | The bookings of the sessions |
| `Tilttek\EscapeRoom\Contract\SessionReservations` | The reservations, as [the bookings](bookings.md) say |
| The contracts of `Application\Contract` | Aliased to the stores and services of the configuration |

## Routes

`tilttek_escape_room_console`, `tilttek_escape_room_console_act`, `tilttek_escape_room_screen`,
`tilttek_escape_room_screen_state`, `tilttek_escape_room_consent` and `tilttek_escape_room_leaderboard`, as
[the screens](screens.md) describe.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Application\Exception\DeskRefused` | A game unknown or already there, a room unknown, a malformed identifier |
| `Tilttek\EscapeRoom\Exception\InvalidRoom`, `GameRefused`, `BookingRefused` | As the library says; a batch of expiries or a leaderboard out of bounds, an identifier of a booking too long |
| `UnexpectedValueException` | A row that does not hold what its column should |

`DeskRefused` implements `Tilttek\EscapeRoom\Exception\EscapeRoomException`, so a page catches every refusal at once.
