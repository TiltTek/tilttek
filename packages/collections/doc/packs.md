# Packs

## Published odds

`Packs::odds(pack)` returns the `Odds` a pack publishes: the chance of each rarity and of each item, before anything is
drawn. A site that makes its members draw at random says at which odds.

## Opening a pack

A pack opens in two steps, so that neither the site nor the member can choose the draw:

1. **Commit.** `commit(member, pack, key)` draws a secret seed of 16 to 64 bytes from the injected `SeedSource` —
   `SecureSeeds` in production, a fixed seed in tests — and returns a `PendingOpening`: the key, the member, the pack
   and the seed in hexadecimal. The site keeps it and shows the member its `commitment()`, the SHA-256 of the seed.
   Nothing is given yet. A `PendingOpening` is rebuilt from what was kept with its constructor.
2. **Open.** The member, having seen the commitment, gives a client seed of 1 to 256 bytes of UTF-8 text: words they
   type, or bytes their own device draws. `open(pending, clientSeed, moment)` draws the pack's items — each on its own:
   a rarity by weight, then an item of that rarity — and gives a copy of each, under the key followed by `#` and the
   place of the item.

The same pending opening and client seed opened again return the opening they made; a key that opened another pack,
or for another member, or from another seed or client seed, is refused.

The draw is the one both seeds and the key give: `Xoshiro256StarStar` seeded by the HMAC-SHA256, under the seed, of
the client seed, a line feed and the key. The site's seed is fixed before the member's client seed is known, and the
member chooses theirs without knowing the site's: the site cannot keep only the seeds whose draw suits it, and the
member cannot try seeds until one gives a legendary. A client seed the site chooses for the member gives no such
guarantee.

## Checking a draw

The `PackOpening` keeps the seed in hexadecimal, its commitment and the client seed. The member checks afterwards that
the seed matches the commitment shown and that `verify(opening)` replays the same items. The verification draws with
the pack as the catalog holds it now: a pack whose sets or weights changed since no longer verifies its old openings.
