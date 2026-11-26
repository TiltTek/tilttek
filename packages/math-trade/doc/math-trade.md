# Running a math trade

A math trade runs in four steps: the participants list their offers and wishes, the lists close, the organiser
solves them with a seed announced beforehand, and each participant reads what they send and receive. This library
does the second half of the work: it checks the lists and finds the trades. Collecting the lists, publishing the
seed, showing addresses and following the parcels belong to the application.

## The trade list

A `TradeList` holds every `Participant`; each participant makes one `Offer` or more, a copy of a game they put in the
trade. An offer lists its wishes, most wished first: the offers it would be traded for.

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

$list = new TradeList([
    new Participant('alice', [
        new Offer('alice-azul', [new Want('bob-catan'), new WantGroup(['carol-wingspan', 'dave-wingspan'])], 'azul'),
        new Offer('alice-ark', [new WantGroup(['carol-wingspan', 'dave-wingspan'])], 'ark-nova'),
    ]),
    new Participant('bob', [new Offer('bob-catan', [new Want('alice-ark'), new Want('dave-wingspan')], 'catan')]),
    new Participant('carol', [new Offer('carol-wingspan', [new Want('alice-azul')], 'wingspan')]),
    new Participant('dave', [new Offer('dave-wingspan', [new Want('bob-catan'), new Want('alice-ark')], 'wingspan')]),
]);
```

Each line of the wish list has a priority: 1 for the first line, 2 for the second, and so on. A `Want` names one
offer; a `WantGroup` names two offers or more at one priority — "any of these" —, of which the offer is traded for one
at most.

The identifiers are the application's own: a participant, an offer and a game are each 1 to 100 bytes of UTF-8
without a control character. An identifier made of digits is as good as any other.

### What a list refuses

The list is checked as it is built, and refused with an `InvalidTradeList` when:

- two participants, or two offers, share an identifier;
- a participant makes no offer;
- an offer wishes for itself, for an offer nobody makes, or for an offer of the same participant;
- an offer wishes for the same offer twice, on two lines or in a group;
- a group names fewer than two offers.

It is refused with a `LimitExceeded` when it is larger than its `Limits` allow: see
[Extension points](extension-points.md#limits).

## Copies of one game

The third argument of an offer names the game the copy is: two copies of Wingspan share the game `wingspan`. It does
one thing: a participant who wishes for a game from two of their offers or more, when two copies or more of that game
are wished for, receives that game **once at most**. Above, Alice wishes for Wingspan from both her offers, and two
copies are offered: she receives one Wingspan, never two.

The solver gives such a participant a node of their own for the game, between their offers and the copies, which can
be passed through once. Copies of a game are then interchangeable for that participant: each of the offers that wish
for the game may receive any copy one of their offers wishes for, at the best priority that offer gave a copy.

An offer without a game is a game of its own: it is never guarded. A participant who wishes for a game from one offer
only, or for which only one copy is wished for, needs no guard: one offer receives one game.

## Solving

```php
use Tilttek\MathTrade\TradeSolver;

$result = new TradeSolver()->solve($list, 'spring-trade-2026');
```

The solver finds, in this order:

1. the most offers traded;
2. among the results that trade as many, the smallest sum of the priorities fulfilled: the first wishes before the
   last;
3. among the results equal on both, the one the seed picks.

An offer is never traded for something it did not wish for, never changes hands twice, and nobody trades with
themselves.

### The seed

The seed is text of 1 to 256 bytes of UTF-8 without a control character. It shuffles the offers before they are
solved, which breaks the ties between results of equal value. The same list and the same seed give the same result,
whatever the order the participants and offers were given in: announce the seed before the lists close — or draw it
afterwards in public, from dice or a lottery draw — then publish it beside the result, so that anyone can solve the
published lists again and check the trades.

An invalid seed is refused with an `InvalidSeed`.

## The result

A `TradeResult` holds:

| Member | What it gives |
|---|---|
| `seed` | The seed it was solved with, to publish beside it |
| `cycles` | The loops of trades, each a `TradeCycle` of `Trade`s, ordered by the identifier of their first offer |
| `unmatched` | The offers no loop takes, by identifier |
| `trades()` | Every `Trade` — offer, giver, receiver —, loop after loop |
| `tradeCount()` | The number of offers that change hands |
| `swaps()` | Every `Swap`, by identifier of the sent offer |
| `forParticipant($id)` | What the participant sends and receives: one `Swap` per offer of theirs that is traded |
| `swapOf($offer)` | The `Swap` the offer is sent in, or null when it is kept |

A `Swap` is the line each participant reads: they send `sent` to `sendTo`, and receive `received` from `receiveFrom`.

```php
foreach ($result->forParticipant('alice') as $swap) {
    printf("Send %s to %s, receive %s from %s\n", $swap->sent, $swap->sendTo, $swap->received, $swap->receiveFrom);
}
```

With the list above and the seed `spring-trade-2026`, every offer is traded, in one loop of five:

```text
Send alice-ark to dave, receive carol-wingspan from carol
Send alice-azul to carol, receive bob-catan from bob
```

Alice receives Bob's Catan, her first wish, and one copy of Wingspan only, though she wished for it from both her
offers; Carol receives Azul, Dave receives Ark Nova, and Bob receives Dave's Wingspan, his second wish. Had Bob
received Ark Nova, his first, Alice would have needed both copies of Wingspan to trade everything: the guard forbids
it, and trading five offers comes before the priorities. The loop is in `cycles`:

```php
foreach ($result->cycles as $cycle) {
    foreach ($cycle->trades as $trade) {
        printf("%s sends %s to %s\n", $trade->giver, $trade->offer, $trade->receiver);
    }
}
```

## From end to end

The participants' lists usually arrive as JSON, on [the format of this library](json-format.md). The organiser reads
them, solves them with the announced seed, publishes the result, and tells each participant their part:

```php
use Tilttek\MathTrade\Exception\MathTradeException;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;

try {
    $list = new TradeListJson()->decode($uploadedJson, new Limits(offers: 3_000));
} catch (MathTradeException $exception) {
    // Show the participant why their list was refused: the message names the offer and the rule.
    return $exception->getMessage();
}

$result = new TradeSolver()->solve($list, $announcedSeed);

printf("%d offers trade hands in %d loops; %d are kept.\n", $result->tradeCount(), \count($result->cycles), \count($result->unmatched));

foreach ($list->participants as $participant) {
    foreach ($result->forParticipant($participant->id) as $swap) {
        // The application looks the participants up, and shows the address of sendTo once the trade is confirmed.
        $notify($participant->id, $swap->sent, $swap->sendTo, $swap->received, $swap->receiveFrom);
    }
}
```

The library never sees a name or an address: it works on identifiers, and the application decides when a participant
may see the address of the one they send to.
