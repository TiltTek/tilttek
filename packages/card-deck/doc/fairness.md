# Fairness

A player trusts the deck when the server could not arrange it. The library takes its randomness from a `Seed` of
`tilttek/dice`, bound by a commitment published before the players add a seed of their own.

## The flow of a game

1. `CardTable::open()` stores the game with a seed drawn from a secure source and returns its commitment,
   `Seed::commitment()`: the SHA-256 of the domain of `tilttek/dice`, the seed and the number 0. The application
   publishes it.
2. The players choose a client seed, up to 256 bytes, after the commitment is published. `CardTable::start()` shuffles
   the draw pile from the seed, the client seed and the number 0.
3. Every later gesture that draws randomness — a `Shuffle`, a `Draw` at random — takes the next number, 1, 2 and so on.
   The numbers follow the journal, so a replay draws the same ones.
4. `CardTable::finish()` reveals the seed: a `Reveal` holds the deck, the seed, the client seed and the journal.

The server fixed its seed before it knew the client seed, and the commitment binds it: it could not choose the order.

## The shuffle

`Shuffler` is the Fisher-Yates shuffle: from the last place to the second, each place swaps with a place drawn among
itself and those before it, by `Randomizer::getInt(0, place)`. The randomizer is the one `Seed::randomizer()` gives:
`Xoshiro256**` seeded with an HMAC-SHA256 of the client seed and the number. Every order is equally likely, and the
same seed, client seed and number give the same order in any language that implements the same engine and the same
draw.

A random draw takes the card at `Randomizer::getInt(0, count - 1)` of the next number.

## Verifying a finished game

```php
$reveal = Reveal::fromArray($published);   // what the server published at the end

$honest = $reveal->matches(Commitment::fromHex($commitmentPublishedAtTheStart));
$state = $reveal->replay();                 // the final state, the same fingerprint as the server's
```

`matches()` checks the seed against the commitment, in constant time. `replay()` applies the journal again; a journal
that does not hold together throws. `Reveal::fromArray()` checks every field and accepts at most 10 000 gestures.

## What the seed does not protect

The server knows the seed during the game, so it knows the order of the draw pile: the commitment proves that the
order was not chosen, not that nobody read it. A store keeps the seed of an unfinished game as a secret — encrypted at
rest, never in a log, never sent to a browser — and reveals it only through `finish()`.
