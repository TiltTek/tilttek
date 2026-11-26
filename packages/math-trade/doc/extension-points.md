# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Limits` | The size of the lists it accepts | 2 000 participants, 5 000 offers, 100 per participant, 500 wishes per offer, 100 offers per group, 250 000 wished offers, 8 MiB of JSON |
| The identifiers | Its own identifiers of participants, offers and games | None |
| The seed | The text that breaks ties, announced or drawn by the application | None |

The library has no interface to implement: the trade list, the limits and the result are values, and `TradeSolver`
and `TradeListJson` keep no state between two calls.

## Limits

The solver's time grows with the number of offers and of wished offers — see [Testing](testing.md#complexity) —, so
every list is checked against its limits before it is solved, and a JSON document against its length before it is
decoded. An application that solves larger trades raises them; one that accepts uploads from anyone lowers them.

```php
use Tilttek\MathTrade\Limits;

$limits = new Limits(
    participants: 500,
    offers: 2_000,
    offersPerParticipant: 20,
    wishesPerOffer: 200,
    groupSize: 50,
    wishedOffers: 100_000,
    jsonBytes: 1_048_576,
);
```

| Limit | Counts | Ceiling |
|---|---|---|
| `participants` | The participants of the list | 100 000 |
| `offers` | The offers of the list | 100 000 |
| `offersPerParticipant` | The offers of one participant | 100 000 |
| `wishesPerOffer` | The lines of one offer's wish list, a group counted once | 10 000 |
| `groupSize` | The offers of one group of wishes | 10 000 |
| `wishedOffers` | The wished offers of the whole list, each offer of a group counted | 5 000 000 |
| `jsonBytes` | The length of a JSON document the list is read from | 256 MiB |

Each limit is 1 to its ceiling; another value is refused with an `InvalidTradeList`.

## What stays in the application

- **Collecting the lists.** A form, a spreadsheet import or a wish-list generator builds the `TradeList`, or the JSON
  document of [the format](json-format.md).
- **The seed.** The application announces it before the lists close, or draws it in public afterwards, and publishes
  it beside the result.
- **Names and addresses.** The library works on identifiers. The application shows each participant the address of
  the one they send to once the trade is confirmed, never before.
- **The parcels.** Following what was sent and received, and the participants who did not send, is the application's.
