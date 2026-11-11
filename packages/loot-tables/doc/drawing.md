# Drawing

## A verifiable draw

A draw is verifiable when the result could not have been chosen. The library follows the commitment of
`tilttek/dice`:

1. **Commit.** `PendingDraw::open($secure, $nonce)` draws a secret seed from a secure source. Its `commitment()`, the
   SHA-256 of the seed, is shown at once — before the payment, on the order. The nonce numbers the draws made from one
   seed. The seed is kept secret; `PendingDraw::restore()` rebuilds the draw from the stored seed.
2. **Client seed.** The buyer, having seen the commitment, gives a client seed of 1 to 256 bytes of UTF-8 text: words they type, or
   bytes their own device draws.
3. **Reveal.** `LootDrawer::reveal($box, $pending, $clientSeed)` rolls the dice of the box from the seed and the client
   seed, one die per table, and returns a `RevealedDraw`: its `LootResult` and the verifiable roll of `tilttek/dice`.
   The same seed, client seed and nonce always give the same faces.
4. **Publish.** `RevealedDraw::proof()` is what is published once the draw is revealed: the box, the faces, the
   commitment, the seed, the client seed, the nonce, the expression, the rounding and the roll.
5. **Verify.** `LootDrawer::verify($box, $proof)` is true when the seed matches the commitment, the expression is the
   box's, rolling the dice again gives the published roll, and the faces are those of the roll. A proof that does not
   read — a field missing, of another type, a seed that is not hexadecimal — is false, never an exception.

The draw is made once: whoever draws keeps the result and gives it again when asked, rather than revealing a second
time.

```php
$pending = PendingDraw::open(new Randomizer(new Secure()));
$store->keep($pending->seed->hex(), $pending->commitment()->hex);  // the commitment is shown

// the buyer gives their client seed, then pays
$revealed = new LootDrawer()->reveal($box, PendingDraw::restore($seed), $clientSeed);
$store->keepResult($revealed->proof());
```

### What the client seed protects

The commitment stops whoever draws from changing the seed after the fact; it does not stop them from opening many
draws, revealing each in private and showing only the commitment of a seed whose result suits them. Only a client seed
they did not know when committing prevents that. A client seed the shop knows or chooses — an order number, a line, a
unit — gives no fairness at all: collect it from the buyer once the commitment is on their screen.

## Results

A `LootResult` is one `Pick` per table — the table, the face its die showed and the entry of that face — and the
`DrawSource` of its faces:

| Source | Faces |
|---|---|
| `Verifiable` | rolled by `LootDrawer` from a committed seed |
| `Physical` | physical dice, rolled in front of a cashier and entered as they fell |

`LootResult::ofFaces($box, $faces, $source)` builds a result from the faces, one per table; `InvalidLoot` refuses a
wrong count or a face a die does not have. `faces()` and `entries()` list them in the order of the tables.

## A text drawn from a list

A text drawn after payment is a box of one table of texts:

```php
$box = LootBox::single(LootTable::ofTexts('epitaphs', 'Epitaphs', $texts));
$text = new LootDrawer()->reveal($box, $pending, $clientSeed)->result->entries()[0]->name;
```
