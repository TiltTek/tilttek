# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: every move of the state machine from every
state, at the last moment it is allowed and the first it is not; the limits of claims and awards; amounts over what is
left; awards shared, closed or settled at the end of the grace; moves made again; an offer, a move and a settlement
raced by another writer; an escrow that fails once and is settled again; references of the kind `erased` refused at
every entry point, nothing changed; the escrow contract, run on the memory escrow and on the ledger of `tilttek/points`;
records refused field by field and as a whole. In the monorepo, Infection holds the library at 100 % of covered mutants.

Two fuzz targets run with `composer fuzz`:

- `tests/Fuzz/record.php`, `bounty-record`: any JSON text gives a bounty that writes back as it was read, or is refused
  with `InvalidBounty`, never another exception.
- `tests/Fuzz/lifecycle.php`, `bounty-lifecycle`: each byte of the input is a move by one of four members or by a
  reference of the kind `erased`; whatever the moves, no point is made or lost, the points are held exactly while the
  bounty is open, the winners are paid, the awards never pass the amount, a move by an `erased` reference is refused
  with `InvalidBounty` and changes nothing, and the bounty reads back as it was written.

`tests/Benchmark/BountiesBench.php` measures, on a bounty holding 200 claims and 19 awards, an award sent again —
the bounty read from its record and checked —, and the record read and checked: under a millisecond each with OPcache.

## Testing an application

Give `Bounties` the memory implementations and a clock that does not move:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Store\InMemoryBountyStore;

$escrow = new InMemoryBountyEscrow();
$escrow->fund($ana, 1000);
$clock = new MockClock('2026-11-13 20:00 UTC');
$bounties = new Bounties(new InMemoryBountyStore(), $escrow, $dispatcher, clock: $clock);

$clock->sleep(9 * 86_400);
$bounties->expireDue();
```

A store of its own passes the same moves as `InMemoryBountyStore` and gives back the same records.
