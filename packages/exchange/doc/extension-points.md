# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `Reservation\ReservationStore`, `Trade\OfferStore`, `Transfer\TransferLog` | The storage of reservations, offers and transfers, in place of the stores in memory | [Writing a store](writing-a-store.md) |
| `Transaction` | The unit that runs the steps of one exchange together, such as a database transaction | [Writing a store](writing-a-store.md) |
| `Trade\Inventory` | The members' items as the application's collections keep them: who owns an item, whether it may change hands, the moves of a trade | [Trades](trades.md) |
| `Reward\Catalog`, `Reward\WritableCatalog` | The rewards offered for points, from a configuration or a catalogue the site edits | [Rewards](rewards.md#the-catalogue) |
| `Reward\Requirements` | The conditions of the rewards — `badge:helper`, `level:5` — answered from the application's other packages | [Rewards](rewards.md#before-reserving) |
| `MemberSince` | When a member joined, for the delay a new account waits | [Guards](guards.md) |
| `Announcer` | What is done with each `Event\ExchangeEvent`: a reward delivered, a member told | [Events](events.md) |
| `Psr\Clock\ClockInterface` | The clock the services read: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
