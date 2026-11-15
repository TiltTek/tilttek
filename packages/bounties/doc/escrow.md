# Escrow

A bounty is paid in advance: its points leave the sponsor's balance when it is offered, and are held until the bounty
is settled — paid to the winners, the rest given back. The library moves them through `Contract\BountyEscrow`.

## The three moves

| Move | When | What it does |
|---|---|---|
| `reserve($hold)` | The bounty is offered | Takes the amount out of the sponsor's points; `InsufficientPoints` when they do not cover it |
| `confirm($hold, $payouts)` | The bounty is awarded | Pays each payout, and gives what the payouts leave back to the sponsor |
| `cancel($hold)` | The bounty is cancelled, or expires with nothing awarded | Gives everything back to the sponsor |

Every move takes the whole `BountyHold`: its key, `bounty:` and the bounty's identifier; the payer; the amount and its
unit of points; when it was held and until when, the end of the grace. Each move is keyed by the hold, so the same move
made twice has the effect of one: a retried request, or `settle()` after an escrow that failed, moves no point twice.
Confirming a hold never held holds it first, so that no point is paid that the payer did not give; cancelling a hold
never held does nothing. The library settles a hold once, confirmed or cancelled, as the state machine of the bounty
makes sure.

The moves follow those of the `PointsHold` contract of `tilttek/exchange-bundle` — hold, confirm, release —, which
take the whole reservation as these take the whole hold.

## The escrows shipped

- `Escrow\PointsBountyEscrow` holds the points in the ledger of `tilttek/points`: a hold is a spending of the
  sponsor's points under the hold's key, on the redemption account `bounties`; a confirmation grants each payout from
  the issuance account `bounties`, under `<key>:payout:` and a digest of the member, and grants back what the payouts
  leave under `<key>:remainder`; a cancellation refunds the spending.
- `Escrow\InMemoryBountyEscrow` keeps the points and the holds in memory, for tests: `fund()` gives a member points,
  `balance()` and `held()` read them, and it refuses with `EscrowConflict` what contradicts a hold — another hold under
  the same key, payouts over the amount, a hold settled one way and then the other.

## An escrow on `tilttek/exchange-bundle`

An application that holds its rewards through the `PointsHold` contract of `tilttek/exchange-bundle` holds its
bounties the same way, with an adapter of its own:

```php
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\Points\Points;

final readonly class ExchangeBountyEscrow implements BountyEscrow
{
    public function __construct(
        private PointsHold $holds,
        private Points $points,
    ) {
    }

    public function reserve(BountyHold $hold): bool
    {
        return $this->holds->hold($this->reservation($hold));
    }

    public function confirm(BountyHold $hold, array $payouts): void
    {
        $this->holds->hold($this->reservation($hold));      // a hold never held is held first
        $this->holds->confirm($this->reservation($hold));
        $paid = 0;

        foreach ($payouts as $payout) {
            $this->points->grant($payout->member, $payout->amount, $hold->key.':payout:'.hash('xxh128', $payout->member->toString()), 'bounties', $hold->unit);
            $paid += $payout->amount;
        }

        if ($paid < $hold->amount) {
            $this->points->grant($hold->payer, $hold->amount - $paid, $hold->key.':remainder', 'bounties', $hold->unit);
        }
    }

    public function cancel(BountyHold $hold): void
    {
        $this->holds->release($this->reservation($hold));
    }

    private function reservation(BountyHold $hold): Reservation
    {
        return new Reservation(ExchangeKey::fromString($hold->key), $hold->payer, 'bounty', 1, $hold->amount, $hold->unit, $hold->heldAt, $hold->until);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Points\ExchangeBountyEscrow: ~
    Tilttek\Bounties\Contract\BountyEscrow: '@App\Points\ExchangeBountyEscrow'
```

Its `available()` then leaves out the bounties held, as it leaves out the rewards reserved. When the ledger of
`tilttek/points` holds pending points of its own, the implementation behind `PointsHold` changes, and this adapter
stays as it is.

## Never money

A bounty is points of the site, in one of its units of `tilttek/points` — `points`, `gems` —: a unit of money is not
a unit of points, and nothing in the library buys, sells or converts points. The library has no field and no adapter
for money, and none is to be added: a bounty kept prepaid and given back automatically when nobody earns it is a game,
not a payment.
