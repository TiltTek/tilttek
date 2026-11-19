# Results

`LootResults` reads the draws of the orders:

| Method | Gives |
|---|---|
| `ofOrder($number)` | the draws of an order, by line, option and unit |
| `draw($id)` | the draw of this identifier, or `null` |
| `result($draw)` | the `LootResult` of a draw revealed or entered — its faces and its entries, with their images and slots —, or `null` |
| `shopCode($draw)` | its shop code, or `null` while it is not revealed or when its box has a die of more than 20 sides |
| `verify($draw)` | whether it is verifiable and its proof checks out: the seed matches the commitment shown before the payment, and the dice rolled again give the same faces |
| `replay($draw)` | the draw rolled again from the seed it revealed, or `null` while the seed is secret and for physical dice |

Production composes what it makes from the entries and their slots, through [`LootPrint`](production-sheet.md); a
receipt shows the line the order kept; the buyer's page shows the commitment before the payment and, after, the seed
and the entries.

## Rolling a draw again

Once revealed, a draw publishes its seed in its proof, `LootDraw::proof()`, and on the buyer's page. Anyone can then
check it, in two steps:

1. the SHA-256 of the seed — its 32 bytes, read from the 64 hexadecimal digits — is the commitment shown before the
   payment;
2. the dice of the box rolled again with `tilttek/loot-tables`, from that seed, with the identifier of the draw as the
   client seed and the nonce 0, give the faces kept.

`LootResults::replay()` makes the second step, `LootResults::verify()` both, and the console does both for one draw:

```sh
php bin/console tilttek:loot-tables:replay 0f5c…e21a
```

The command writes the commitment, the seed, the client seed and the entries the dice give again, and succeeds only
when they are the ones kept and the proof checks out against the commitment. It fails for an identifier no draw has,
and for a draw whose seed is still secret or whose dice were entered at a register.

## The stored draws

A `LootDraw` is the draw of one unit:

| Method | Gives |
|---|---|
| `id()`, `orderNumber()`, `line()`, `option()`, `unit()`, `drawer()` | which unit it is for |
| `commitment()` | the commitment shown, or `null` for physical dice |
| `revealedSeed()` | the seed, once revealed; `null` while it is secret and for physical dice |
| `clientSeedOfReveal()` | the client seed the roll takes: the identifier of the draw |
| `texts()` | the entries a text was committed with |
| `isRevealed()`, `drawn()`, `proof()`, `result($box)` | what it gave, once revealed or entered |
| `createdAt()`, `revealedAt()` | when |
