# Escrow

The prize of a challenge is points the creator gives up when the challenge is created, held until the challenge is
settled: paid to the winner, or given back to the creator. The library moves them through `Contract\PrizeEscrow`.

## The three moves

| Move | When | What it does |
|---|---|---|
| `reserve($hold)` | The challenge is created | Takes the prize out of the creator's points; `InsufficientPoints` when they do not cover it |
| `confirm($hold, $payouts)` | The challenge is won | Pays each payout, and gives what the payouts leave back to the creator |
| `cancel($hold)` | The challenge is cancelled, expires or is removed | Gives the prize back to the creator |

Every move takes the whole `PrizeHold`: its key, `friend-challenge:` and the challenge's identifier; the payer; the
amount and its unit of points; when it was held and until when, the end of the review. Each move is keyed by the hold,
so the same move made twice has the effect of one: a retried request, or `settle()` after an escrow that failed, moves
no point twice. Confirming a hold never held holds it first, so that no point is paid that the payer did not give;
cancelling a hold never held does nothing. The library settles a hold once, confirmed or cancelled, as the state
machine of the challenge makes sure.

The moves follow those of the `PointsHold` contract of `tilttek/exchange-bundle` — hold, confirm, release —, which
take the whole reservation as these take the whole hold.

## The escrows shipped

- `Escrow\PointsPrizeEscrow` holds the prize in the ledger of `tilttek/points`: a hold is a spending of the creator's
  points under the hold's key, on the redemption account `friend-challenges`; a confirmation grants each payout from
  the issuance account `friend-challenges`, under `<key>:payout:` and a digest of the member, and grants back what the
  payouts leave under `<key>:remainder`; a cancellation refunds the spending. The points leave the creator's balance
  when they are held.
- `Escrow\InMemoryPrizeEscrow` keeps the points and the holds in memory, for tests: `fund()` gives a member points,
  `balance()` and `held()` read them, and it refuses with `EscrowConflict` what contradicts a hold — another hold under
  the same key, payouts over the amount, a hold settled one way and then the other.

## An escrow on `tilttek/exchange-bundle`

An application that holds its rewards through the `PointsHold` contract of `tilttek/exchange-bundle` holds its prizes
the same way, with an adapter of its own:

```php
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\Points\Points;

final readonly class ExchangePrizeEscrow implements PrizeEscrow
{
    public function __construct(
        private PointsHold $holds,
        private Points $points,
    ) {
    }

    public function reserve(PrizeHold $hold): bool
    {
        return $this->holds->hold($this->reservation($hold));
    }

    public function confirm(PrizeHold $hold, array $payouts): void
    {
        $this->holds->hold($this->reservation($hold));      // a hold never held is held first
        $this->holds->confirm($this->reservation($hold));
        $paid = 0;

        foreach ($payouts as $payout) {
            $this->points->grant($payout->member, $payout->amount, $hold->key.':payout:'.hash('xxh128', $payout->member->toString()), 'friend-challenges', $hold->unit);
            $paid += $payout->amount;
        }

        if ($paid < $hold->amount) {
            $this->points->grant($hold->payer, $hold->amount - $paid, $hold->key.':remainder', 'friend-challenges', $hold->unit);
        }
    }

    public function cancel(PrizeHold $hold): void
    {
        $this->holds->release($this->reservation($hold));
    }

    private function reservation(PrizeHold $hold): Reservation
    {
        return new Reservation(ExchangeKey::fromString($hold->key), $hold->payer, 'friend-challenge', 1, $hold->amount, $hold->unit, $hold->heldAt, $hold->until);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Points\ExchangePrizeEscrow: ~
    Tilttek\FriendChallenges\Contract\PrizeEscrow: '@App\Points\ExchangePrizeEscrow'
```

Its `available()` then leaves out the prizes held, as it leaves out the rewards reserved. When the ledger of
`tilttek/points` holds pending points of its own, the implementation behind `PointsHold` changes, and this adapter
stays as it is.

## Never money

The prize is points of the site, in one of its units of `tilttek/points` — `points`, `gems` —: a unit of money is
not a unit of points, and nothing in the library buys, sells or converts points. The library has no field and no
adapter for money.
