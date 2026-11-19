<!-- phil-dev source-sha256=5ee5040232df923497d6109e69d00dfb7388c02f75103c88e3abc5231e5ada82 -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `TilttekEscapeRoomBundle` | Le bundle et sa configuration `tilttek_escape_room` |
| `Application\GameDesk` | Les coups des parties par leur identifiant |
| `Application\StoredGame` | Une partie : son identifiant, sa salle et son état |
| `Application\Screen`, `Application\ScreenKind` | Ce que montre l'écran d'indices |
| `Application\EscapeRoomTopics` | Le sujet Mercure d'une partie |
| `Application\ConfiguredRoomCatalog` | Les salles de la configuration |
| `Application\NoEscapeRoomAccess` | L'accès qui refuse tout spectateur |
| `Application\UnnamedTeams` | Les noms d'aucune équipe |
| `Application\Contract\*` | `RoomCatalog`, `EscapeRoomAccess`, `TeamNames`, `GameStore`, `ResultStore`, `ScreenStore`, `PartyBookings`, `ScreenPublisher` |
| `Infrastructure\Doctrine\EscapeRoomTables`, `EscapeRoomSchemaListener` | Les cinq tables, ajoutées au schéma généré |
| `Infrastructure\Doctrine\DbalGameStore`, `DbalResultStore`, `DbalScreenStore`, `DbalSeatLedger` | Les magasins sur Doctrine DBAL |
| `Infrastructure\Memory\InMemoryGameStore`, `InMemoryResultStore`, `InMemoryScreenStore`, `InMemorySeatLedger` | Les magasins en mémoire |
| `Infrastructure\Scheduler\EscapeRoomSchedule`, `ExpireDue`, `ExpireDueHandler` | L'horaire des expirations |
| `Infrastructure\Console\ExpireCommand` | `tilttek:escape-room:expire` |
| `Infrastructure\Mercure\MercureScreenPublisher`, `ScreenSubscriptions` | Les coups sur Mercure, et les abonnements des spectateurs |
| `Infrastructure\Http\ConsoleController`, `ScreenController`, `ConsentController`, `LeaderboardController` | Les pages |
| `Infrastructure\Http\Screens`, `Views` | Ce que les pages partagent, et ce que lisent les gabarits |
| `Infrastructure\Twig\EscapeRoomExtension`, `KitComponents` | Les fonctions Twig et les noms des recettes du kit |
| `Infrastructure\Privacy\EscapeRoomPrivacy` | L'export et l'effacement d'un membre |
| `Infrastructure\DependencyInjection\EscapeRoomServices`, `MercureHubPass`, `KitSubdirectoryPass` | Les services et leur branchement |

## Services

| Service | Ce que c'est |
|---|---|
| `Tilttek\EscapeRoomBundle\Application\GameDesk` | Le bureau, public |
| `Tilttek\EscapeRoom\EscapeRooms` | Les parties de la bibliothèque, qui informent le répartiteur |
| `Tilttek\EscapeRoom\Booking\SessionBooker` | Les réservations des séances |
| `Tilttek\EscapeRoom\Contract\SessionReservations` | Les réservations, comme le disent [les réservations](bookings.md) |
| Les contrats de `Application\Contract` | Pointent vers les magasins et les services de la configuration |

## Routes

`tilttek_escape_room_console`, `tilttek_escape_room_console_act`, `tilttek_escape_room_screen`,
`tilttek_escape_room_screen_state`, `tilttek_escape_room_consent` et `tilttek_escape_room_leaderboard`, comme le
décrivent [les écrans](screens.md).

## Exceptions

| Exception | Lancée quand |
|---|---|
| `Application\Exception\DeskRefused` | Une partie inconnue ou déjà là, une salle inconnue, un identifiant mal formé |
| `Tilttek\EscapeRoom\Exception\InvalidRoom`, `GameRefused`, `BookingRefused` | Comme le dit la bibliothèque ; un lot d'expirations ou un classement hors bornes, un identifiant de réservation trop long |
| `UnexpectedValueException` | Une ligne qui ne contient pas ce que sa colonne devrait contenir |

`DeskRefused` implémente `Tilttek\EscapeRoom\Exception\EscapeRoomException`, de sorte qu'une page attrape d'un coup
tous les refus.
