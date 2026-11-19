# Rewards

## The catalogue

With `catalog: configuration`, the rewards are those of `rewards`. With `catalog: doctrine`, they live in the table
`tilttek_exchange_reward`, which the application edits through the `Catalog` alias, a `WritableCatalog`:

```php
use Tilttek\Exchange\Reward\Catalog;
use Tilttek\Exchange\Reward\Reward;

$catalog->save(new Reward('seat', 250, stock: 30, perMember: 1));
$catalog->remove('discount-10');
```

## Reserving

`Redemptions` is a public service. A controller or a handler reserves with a key built from what makes the exchange
unique — an order, a member and a reward —, so that a retried request reserves once:

```php
$reservation = $redemptions->reserve($member, 'seat', 'order:'.$order->id.':seat');
```

The reasons a member cannot reserve — `refusal()` — and the units left — `remaining()` — fill a page of rewards.

## Delivering

A listener of `Tilttek\Exchange\Event\RewardReserved` delivers, then confirms; or cancels when it cannot, and the
points come back:

```php
#[AsEventListener]
final readonly class DeliverSeat
{
    public function __construct(private Redemptions $redemptions, private Tickets $tickets) {}

    public function __invoke(RewardReserved $event): void
    {
        if ('seat' !== $event->reservation->reward) {
            return;
        }

        $this->tickets->issue($event->reservation->member, $event->reservation->key->value);
        $this->redemptions->confirm($event->reservation->key->value);
    }
}
```

`tilttek:exchange:expire`, run by a scheduler, expires the reservations past their lifetime.

## The points a reservation holds

`Tilttek\ExchangeBundle\Application\Contract\PointsHold` names the three moves a reserved reward makes on the
member's points, each keyed by the reservation and safe to run twice:

| Method | When | Effect |
|---|---|---|
| `hold(Reservation)` | The reward is reserved | The amount leaves the available balance; `InsufficientPoints` when it does not cover it, and nothing is held. Returns whether this call held it. |
| `confirm(Reservation)` | The reward is delivered | The held points stay spent. |
| `release(Reservation)` | The reservation is cancelled, refused or expires | The held points come back to the member. |
| `held(member, currency)` | — | The points held and not yet confirmed or released. |
| `available(member, currency)` | — | The balance less the points held: what a new reservation may spend. |

A reservation is settled once, confirmed or released and never both, as the store's `transition()` makes sure.

The bundle registers `SpendingPointsHold` under the `PointsHold` alias. It holds by spending the points at once and
gives them back by a refund, through the `Points` service: the spending is written under the reservation's spending
key, `exchange:reservation:` and its key, on the account `reward:` and the reward's code — the very movements
`Redemptions` writes. A hold and a reservation of the same key are therefore one spending, and either side may give it
back. Since the points leave the balance when they are held, `held()` is always 0 and `available()` is the balance.

An application that holds points outside `Redemptions` — a reward granted by hand, a reservation taken by another
channel — goes through the alias, so that a hold kept as a pending entry of the ledger replaces `SpendingPointsHold`
without changing its code:

```php
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;

$hold->hold($reservation);
// … the application delivers …
$hold->confirm($reservation);
```

