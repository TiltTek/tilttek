# Reference

| Class | Role |
|---|---|
| `TilttekExchangeBundle` | The bundle, its configuration and the privacy module |
| `Application\Contract\PointsHold` | The points of a reserved reward held, kept or given back |
| `Infrastructure\Points\SpendingPointsHold` | The hold as a spending of `Points`, given back by a refund |
| `Infrastructure\DependencyInjection\ExchangeServices` | The services, checked when the container is compiled |
| `Infrastructure\Doctrine\ExchangeTables` | The seven tables |
| `Infrastructure\Doctrine\ExchangeSchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Doctrine\Rows` | The idempotent insertion per platform; the columns read back |
| `Infrastructure\Doctrine\DbalCatalog` | The catalogue in its table |
| `Infrastructure\Doctrine\DbalReservationStore` | The reservations, under the reward's lock row |
| `Infrastructure\Doctrine\DbalTransferLog` | The journal of transfers |
| `Infrastructure\Doctrine\DbalOfferStore` | The offers, their items and their holds |
| `Infrastructure\Doctrine\DbalTransaction` | The steps of a trade in one transaction |
| `Infrastructure\Event\DispatchingAnnouncer` | The events to the event dispatcher |
| `Infrastructure\Form\RedeemRewardType`, `TransferPointsType`, `OfferType` | The forms |
| `Infrastructure\Console\ExpireCommand`, `TransfersCommand` | The commands |
| `Infrastructure\Privacy\ExchangePrivacy` | The export and the erasure for `phil/privacy-bundle` |
