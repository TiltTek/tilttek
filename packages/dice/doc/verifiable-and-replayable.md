# Verifiable and replayable rolls

## Verifiable rolls

A verifiable roll lets a player check that the result was not chosen:

1. The roller draws a `Seed` from a secure source and publishes its `Commitment`, the SHA-256 of the seed.
2. The player sends the expression and a seed of their own, the client seed.
3. The roller rolls with `FairRoller::roll($pool, $seed, $clientSeed, $nonce)` — the nonce numbers the rolls made with
   the same seed — and gets a `VerifiableRoll`.
4. Once the rolls of that seed are done, the roller reveals the seed. `VerifiableRoll::toArray()` is what it publishes,
   the rounding of the roll's divisions included.
5. Anyone checks with `FairRoller::verify()` that the seed matches the commitment and that rolling again, with the same
   rounding, gives the same result.

The roller could not choose the result: its seed was fixed before the player's was known. The whole language is
covered: instructions, the dice of instructions, groups, the items drawn from text lists, branches, repetitions and
sentences are rolled from the same seed, and their results are part of what is published and compared. The dice come from
`Xoshiro256**` seeded with the HMAC-SHA256 of the client seed and the nonce under the seed.

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;

$seed = Seed::generate(new Randomizer(new Secure()));
$commitment = $seed->commitment()->hex;              // published now
$roll = new FairRoller()->roll($pool, $seed, 'chosen by the player', 1);
$published = $roll->toArray();                        // published with the seed, later
```

## Replayable rolls

A replayable roll keeps an identifier — 32 hexadecimal digits — and a seed, both drawn from a secure source, and rolls
from the seed. `Replayer::replay($id, $pool, $seed, $rounding)` rolls it again, die for die; the rounding, kept in
`PoolResult::$rounding`, is passed again when it is not the default. A journal keeps the identifier, the
expression, the seed and the result; `tilttek/dice-bundle` keeps them in Doctrine.

Both rest on the stability of PHP's seeded engines and of `Randomizer::getInt()`, which give the same numbers for the
same seed from one PHP version to the next.
