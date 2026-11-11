# tilttek/loot-tables

**Loot tables rolled with any die — committed before, revealed after, and checked by anyone.**

tilttek/loot-tables describes what a product may hold as tables of entries, each rolled with one die: six entries
with a d6, eight with a d8, twenty with a d20. Every entry has a name, an image and the slot it fills — a head, a
hand, a belt. A box declares the tables a product rolls, in order: six tables of six entries roll 6d6. The draw is
verifiable: its secret seed is committed before anyone knows the result, the dice are rolled from it through
`tilttek/dice`, and the published proof lets anyone roll them again. Physical dice, rolled in front of a cashier, are
entered as they fell. A text drawn from a list is a table of one die. A result writes itself as a shop code, one
character per die, and as a line a receipt shows and a program reads back.

It is for any PHP code that sells or hands out what nobody chose: a loot box of a shop, a mystery item of a game, a
random text printed on a product. `tilttek/loot-tables-bundle` integrates it with Symfony and with the options of a
commerce order.

It is a plain PHP library that depends only on `tilttek/dice`.

## Status

| Part | State |
|---|---|
| Entries, tables of any die, boxes, catalogues and their reader | Implemented and tested |
| Verifiable draws: pending draw, reveal, proof and verification | Implemented and tested |
| Results of physical dice, texts drawn from a list | Implemented and tested |
| Shop codes and draw texts, written and read back, fuzzed | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and a first draw. |
| [Tables and boxes](doc/tables-and-boxes.md) | `LootEntry`, `LootTable`, `LootBox`, `LootCatalog`, `CatalogReader`, and their limits. |
| [Drawing](doc/drawing.md) | `PendingDraw`, `LootDrawer`, `RevealedDraw`, the proof and its verification, physical dice. |
| [Codes](doc/codes.md) | `ShopCode` and `DrawText`. |
| [Exceptions](doc/exceptions.md) | `LootException` and its classes. |
| [Extension points](doc/extension-points.md) | Where the application plugs in. |
| [Testing](doc/testing.md) | The library's tests, its fuzz targets, and how to test code that uses it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/loot-tables
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Draw\LootDrawer;
use Tilttek\LootTables\Draw\PendingDraw;
use Tilttek\LootTables\Reading\CatalogReader;

$catalog = new CatalogReader()->read($decodedYaml);
$box = $catalog->box('starter');

$pending = PendingDraw::open(new Randomizer(new Secure()));
$commitment = $pending->commitment()->hex;      // shown to the buyer before the payment

// once, after the payment, with the client seed the buyer typed after seeing the commitment
$revealed = new LootDrawer()->reveal($box, $pending, $buyerClientSeed);
ShopCode::write($revealed->result);             // "352614"
$proof = $revealed->proof();                    // published: anyone verifies it
new LootDrawer()->verify($box, $proof);         // true
```

## Requirements

PHP 8.5 or later, the `mbstring` extension, and `tilttek/dice`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
