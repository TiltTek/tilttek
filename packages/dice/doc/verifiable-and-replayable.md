# Verifiable and replayable rolls

## Verifiable rolls

A verifiable roll lets a player check that the result was not chosen. The library gives the primitives of each step
of the protocol; keeping their order is the host application's part.

1. **Seed and nonce.** The roller draws a `Seed` from a secure source and reserves the nonce of the roll: the number of
   the roll under that seed, which the application hands out once, atomically — a unique key on the seed and the
   nonce, or a counter advanced in the same transaction — so that two requests never share a nonce.
2. **Commitment, before the client seed.** The roller publishes `$seed->commitment($nonce)`, a `Commitment` to the seed
   and to that nonce. It holds for that roll only: the commitment of another nonce of the same seed is another value.
3. **Client seed.** Having seen the commitment, the player sends the expression and a seed of their own, the client
   seed: empty, or up to 256 bytes, `Seed::MAX_CLIENT_SEED`. A longer one is refused with `InvalidDefinition`, so that
   a seed sent in a public request costs a bounded HMAC. A client seed the roller knows or chooses gives no fairness.
4. **Roll.** The roller rolls with `FairRoller::roll($pool, $seed, $clientSeed, $nonce)` and gets a `VerifiableRoll`,
   whose commitment is the one published in step 2.
5. **Reveal, after.** Once the rolls of that seed are done, the roller reveals the seed. `VerifiableRoll::toArray()` is
   what it publishes, the rounding of the roll's divisions included.
6. **Verify.** Anyone checks with `FairRoller::verify()` that the seed and the nonce match the commitment and that
   rolling again, with the same rounding, gives the same result.

The roller could not choose the result: its seed was fixed before the player's was known, and a commitment shown for
one roll does not vouch for a roll made under another nonce. The whole language is covered: instructions, the dice of
instructions, groups, the items drawn from text lists, branches, repetitions and sentences are rolled from the same
seed, and their results are part of what is published and compared. The dice come from `Xoshiro256**` seeded with the
HMAC-SHA256 of the client seed and the nonce under the seed.

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;

$seed = Seed::generate(new Randomizer(new Secure()));
$nonce = $reservedNonce;                              // reserved once, atomically, by the application
$commitment = $seed->commitment($nonce)->hex;         // published now, before the client seed
$roll = new FairRoller()->roll($pool, $seed, 'chosen by the player', $nonce);
$published = $roll->toArray();                        // published with the seed, later
```

### The commitment format

A commitment is 64 lower-case hexadecimal digits: the SHA-256 of

| Part | Bytes |
| --- | --- |
| `Commitment::FORMAT`, `tilttek/dice commitment v2`, in ASCII | 26 |
| A zero byte | 1 |
| The seed | 32 |
| The nonce, a signed 64-bit integer in big-endian order, two's complement | 8 |

Every part has a fixed length, so no two seeds and nonces give the same input, and the domain names the format and its
version: any tool with SHA-256 recomputes a commitment. With the seed `000102…1e1f` — the bytes 0 to 31 — the
commitment of the nonce 0 is `dc3f2c443dcbc5f1aef5f7c2f1b854110296650bc69659002cf66c9627220bc4`, and of the nonce 7
`15d153b1c8c2bbb2c7cdad9c1acc90c56ca83a0f9acd6db1a1895f4440a9fb47`.

A commitment computed in another way — the SHA-256 of the seed alone, say — matches no seed and no nonce: `verify()`
returns false for it, never true.

## Replayable rolls

A replayable roll keeps an identifier — 32 hexadecimal digits — and a seed, both drawn from a secure source, and rolls
from the seed. `Replayer::replay($id, $pool, $seed, $rounding)` rolls it again, die for die; the rounding, kept in
`PoolResult::$rounding`, is passed again when it is not the default. A journal keeps the identifier, the
expression, the seed and the result; `tilttek/dice-bundle` keeps them in Doctrine.

Both rest on the stability of PHP's seeded engines and of `Randomizer::getInt()`, which give the same numbers for the
same seed from one PHP version to the next.
