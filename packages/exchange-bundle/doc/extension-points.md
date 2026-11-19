# Extension points

| Service or alias | Public | What it is |
|---|---|---|
| `Tilttek\Exchange\Reservation\Redemptions` | Yes | Reserve, confirm, cancel, expire |
| `Tilttek\Exchange\Transfer\Transfers` | Yes, when enabled | Give points; the journal |
| `Tilttek\Exchange\Trade\Trades` | Yes, when enabled | Offer, accept, decline, withdraw, counter, expire |
| `Tilttek\Exchange\ExchangeErasure` | Yes | Erase a member's exchanges |
| `Tilttek\Exchange\Reward\Catalog` | Yes | The catalogue, a `WritableCatalog` with `catalog: doctrine` |
| `Tilttek\Exchange\Reservation\ReservationStore`, `…\Transfer\TransferLog`, `…\Trade\OfferStore`, `…\Transaction` | Yes | The stores and the transaction |
| `Tilttek\ExchangeBundle\Application\Contract\PointsHold` | Yes | The points of a reserved reward held, kept or given back; `SpendingPointsHold` by default |
| `Tilttek\Exchange\Announcer` | No | `DispatchingAnnouncer`, to the event dispatcher |

The application provides, by service id in the configuration: the `Requirements` of the rewards' conditions, the
`MemberSince` of the joining dates, and the `Inventory` of the items. It may replace an alias with its own store, or `PointsHold` with
its own hold of points.

The bundle uses the `Points` and `Currencies` services of `tilttek/points-bundle`, and the `clock` service.
