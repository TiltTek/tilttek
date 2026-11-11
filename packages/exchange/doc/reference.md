# Reference

| Type | Role |
|---|---|
| `ExchangeKey` | The key that makes an exchange written once |
| `Refusal` | Why an exchange is refused |
| `Reward\Reward` | A reward: price, currency, stock, availability, limit, conditions |
| `Reward\Catalog`, `Reward\WritableCatalog` | The rewards of a site; a catalogue the application edits |
| `Reward\InMemoryCatalog` | The catalogue in memory, from rewards or a configuration |
| `Reward\Requirements`, `Reward\NoRequirements` | The conditions of the rewards, checked by the application; none met |
| `Reservation\Reservation`, `Reservation\ReservationStatus` | A reservation and where it stands |
| `Reservation\ReservationStore`, `Reservation\InMemoryReservationStore` | Where the reservations are kept |
| `Reservation\Redemptions` | Reserve, confirm, cancel, expire; refusals and units left |
| `Transfer\Transfer` | Points one member gave another |
| `Transfer\TransferLog`, `Transfer\InMemoryTransferLog` | The journal of transfers |
| `Transfer\Transfers` | Give points within the guards; the journal |
| `Trade\Offer`, `Trade\OfferStatus` | An offer of a trade and where it stands |
| `Trade\ItemMove` | An item changing hands |
| `Trade\Inventory`, `Trade\InMemoryInventory` | The members' items, as the application keeps them |
| `Trade\OfferStore`, `Trade\InMemoryOfferStore` | Where the offers and their holds are kept |
| `Trade\Trades` | Offer, accept, decline, withdraw, counter, expire |
| `Allowance` | How much a member may give over a period |
| `Guards` | Self-exchange, new accounts, allowance |
| `MemberSince`, `NoMemberSince` | When a member joined, as the application tells it; no one known |
| `Transaction`, `ImmediateTransaction` | The steps of an exchange as one unit; run as they come |
| `Announcer`, `SilentAnnouncer` | Told each event; tells no one |
| `Event\ExchangeEvent` and its eleven events | [Events](events.md) |
| `Erasure`, `ExchangeErasure` | What erasing a member did; the erasure |
| `Exception\ExchangeException` and its six exceptions | [Exceptions](exceptions.md) |
