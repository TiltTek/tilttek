# Verifying

## The commitment

A roll's commitment is the SHA-256, in 64 lower-case hexadecimal digits, of the canonical JSON of its opening:

```text
["tilttek-secret-rolls/1", roll, scene, position, expression, rounding, seed, result, context]
```

The canonical JSON is PHP's `json_encode()` with `JSON_UNESCAPED_SLASHES`, `JSON_UNESCAPED_UNICODE` and
`JSON_PRESERVE_ZERO_FRACTION`: no space, no escaped slash, characters outside ASCII written as UTF-8. The expression is
in the canonical form of `tilttek/dice`, the rounding is the name of a PHP `RoundingMode` case, the seed is 64
hexadecimal digits and the result is the list `PoolResult::toArray()` writes.

## Checking a revealed roll

`RollVerifier::verify($opening, $commitment)` is true when:

1. the opening hashes to the commitment published when the roll was made;
2. the rounding is a `RoundingMode` PHP knows, and the expression reads, within the limits of the `DiceRoller`, to a
   pool whose canonical form is the expression;
3. rolling the pool again with `DiceRoller::rollVerifiable()` — the seed, the roll's identifier as the client seed,
   nonce 0, the rounding — gives the result revealed.

`verifyPublished($data, $hex)` takes the opening and the commitment as published, and is false for data that does not
read as either. A forged opening costs no more than an honest one: the expression is read within the limits of the
dice.

## The journal

Each entry of a scene's journal carries the hash of the entry before it — 64 zeros for the first — and its own hash,
the SHA-256 of the canonical JSON of:

```text
["tilttek-secret-rolls/1", scene, position, kind, roll, commitment, roller, opening, at, previous]
```

where `at` is written in UTC to the microsecond, `2026-11-12T20:15:03.250000Z`. An entry removed, changed or moved
breaks every hash after it.

`SceneJournal::problems()` lists what breaks a journal, one sentence per fault, in the order of the entries:

- an entry of another scene, or at a position other than its place;
- an entry that does not carry the hash of the one before it, or does not match its own hash;
- a roll committed after the closing, twice, or under an identifier other than the one its position gives;
- a reveal of a roll not committed before, revealed already, of an opening of another scene or position, or of an
  opening that does not match the commitment published or does not roll again to its result;
- a second closing.

`holds()` is true when the list is empty. The game master's own tools cannot hide a fault: the check reads only the
public journal.

## The export a player checks alone

```php
$json = $rolls->journal('scene:12')->toJson();          // published, or offered for download
$problems = SceneJournal::fromJson($json)->problems();  // on the player's side
```

`toJson()` writes the journal in the canonical JSON its hashes are computed over. `fromJson()` reads at most 32 MiB,
64 levels deep, every entry and opening checked field by field — an opening with a field it does not know is refused,
so that nothing shown escapes the commitment —, and throws `InvalidSecretRoll` for anything else; whether the journal
holds is `problems()`'s to tell.

A player without PHP checks the commitments and the chain of hashes with any SHA-256 tool and a JSON writer that
follows the rules above; rolling a result again needs `tilttek/dice`, whose verifiable rolls draw from `Xoshiro256**`
seeded with the HMAC-SHA256 of the client seed and the nonce under the seed.
