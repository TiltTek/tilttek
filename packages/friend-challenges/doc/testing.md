# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: every move of the state machine from every
state, at the last moment it is allowed and the first it is not; the limits of participants, proofs and invitees; votes,
changed votes and the votes needed; moves made again; a creation, a move and an expiry raced by another writer; an
escrow that fails once and is settled again; references of the kind `erased` refused at every entry point, nothing
changed; the escrow contract, run on the memory escrow and on the ledger of `tilttek/points`; records refused field by
field and as a whole. In the monorepo, Infection holds the library at 100 % of covered mutants.

Two fuzz targets run with `composer fuzz`:

- `tests/Fuzz/record.php`, `challenge-record`: any JSON text gives a challenge that writes back as it was read, or is
  refused with `InvalidChallenge`, never another exception.
- `tests/Fuzz/lifecycle.php`, `challenge-lifecycle`: each byte of the input is a move by one of six members or by a
  reference of the kind `erased`; whatever the moves, no point is made or lost, the prize is held exactly while the
  challenge is open or frozen, a winner is paid, a move by an `erased` reference is refused with `InvalidChallenge`
  and changes nothing, and the challenge reads back as it was written.

`tests/Benchmark/FriendChallengesBench.php` measures, on a challenge holding the most it takes — 100 participants and
200 proofs —, a proof rejected, read from its record and written back, and the record read and checked: about 1.5 ms
each with OPcache.

## Testing an application

Give `FriendChallenges` the memory implementations and a clock that does not move:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\Escrow\InMemoryPrizeEscrow;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;

$escrow = new InMemoryPrizeEscrow();
$escrow->fund($ana, 1000);
$friends = new InMemoryFriendship();
$friends->befriend($ana, $bea);
$clock = new MockClock('2026-11-13 20:00 UTC');
$challenges = new FriendChallenges(new InMemoryChallengeStore(), $escrow, $friends, new InMemoryModeration($moderator), $dispatcher, clock: $clock);

$clock->sleep(11 * 86_400);
$challenges->expireDue();
```

A store of its own passes the same moves as `InMemoryChallengeStore` and gives back the same records.
