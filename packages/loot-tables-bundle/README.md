# tilttek/loot-tables-bundle

**Loot tables for Symfony and its commerce orders: committed when the order is placed, drawn once it is paid.**

tilttek/loot-tables-bundle integrates `tilttek/loot-tables` with a Symfony application and with the options of
`phil/commerce-bundle`. The tables and the boxes are declared in the configuration; a product declares the box it
rolls through the drawer of a draw option, `loot-box:<key>`, and a text option drawn among its entries names
`loot-text`. When the order workflow places the order, `LootCommitments` commits one draw per unit — a seed drawn from
the cryptographic generator, kept secret, its commitment shown to the buyer before the payment. Once the order is
paid, the commerce core asks `LootOptionDrawer`, which reveals each draw once from the server's seed alone, without
JavaScript: the order line keeps its faces and the names of its entries, or the text drawn, and the bundle keeps its
proof, which publishes the seed, in Doctrine. At a convention, `PhysicalDice` enters the dice a buyer rolled in front of the cashier, one shop code per
unit, through the form `PhysicalDiceType` that the register's page draws with `<twig:TilttekLootTables:PhysicalDice>`.
On the buyer's page, `<twig:TilttekLootTables:Commitments>` shows the commitments, then the seeds revealed and the
entries, drawn with the recipes of the UX kit. `LootResults` gives production, receipts and buyers the entries with
their images and slots, the shop code, whether a draw checks out, and the draw rolled again from its seed, which
`tilttek:loot-tables:replay` does from the console; `LootPrint` composes what is printed of each unit, and
`LootSheetPdf` prints it as a production sheet.

## Status

| Part | State |
|---|---|
| Tables and boxes from the configuration, checked when the container is compiled | Implemented and tested |
| Seeds from the cryptographic generator, committed when the order workflow places the order | Implemented and tested |
| Reveal once after payment from the server's seed alone, through the commerce option drawer | Implemented and tested |
| A revealed draw rolled again from its seed, and the console command that checks it | Implemented and tested |
| Texts drawn among the entries of a text option | Implemented and tested |
| Physical dice entered at a register | Implemented and tested |
| Draws kept in Doctrine with their proof, results, shop codes and verification | Implemented and tested |
| The buyer's draws without JavaScript: commitments, then seeds and entries, on the recipes of the UX kit | Implemented and tested |
| The form of the physical dice and its component, for a register | Implemented and tested |
| What is printed of each unit, read from the results, and the production sheet in PDF, with `phil/paged-media` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle and its table. |
| [Configuration](doc/configuration.md) | Every key of `tilttek_loot_tables` and the services it registers. |
| [Drawing orders](doc/drawing-orders.md) | The drawers of the options, the commitments when the order is placed, the server's seed alone, the reveal after payment, texts drawn from a list. |
| [Physical dice](doc/physical-dice.md) | `PhysicalDice` at a register. |
| [Results](doc/results.md) | `LootResults`, the stored draws, their verification, and a draw rolled again from its seed. |
| [Screens](doc/screens.md) | The buyer's draws and the form of the physical dice. |
| [Production sheet](doc/production-sheet.md) | `LootPrint`, what is printed of each unit, and `LootSheetPdf`, the draws of an order in PDF. |
| [Exceptions](doc/exceptions.md) | `LootDrawRefused` and the exceptions of the library. |
| [Extension points](doc/extension-points.md) | Where the application plugs in. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every type of the bundle, with its role. |

## Quick start

```sh
composer require tilttek/loot-tables-bundle
```

```yaml
# config/packages/tilttek_loot_tables.yaml
tilttek_loot_tables:
    tables:
        rings:
            name: Rings
            entries:
                - { key: copper, name: Copper ring, image: /loot/copper.png, slot: finger }
                - { key: silver, name: Silver ring, image: /loot/silver.png, slot: finger }
                # … one entry per side of the die
    boxes:
        starter: { name: Starter box, tables: [rings, rings] }
```

```php
// the catalogue of the commerce core declares the option
OptionDefinition::draw('loot', 'Loot', Drawers::box('starter'));

$lifecycle->place($order, $clock->now());                // commits the draws of the order
$optionDraws->draw($order, $payments->summary($order)); // once it is paid
```

```twig
{# the order page: the commitments before the payment, the seeds and the entries after #}
<twig:TilttekLootTables:Commitments order="{{ order.number }}" />
```

```sh
php bin/console tilttek:loot-tables:replay <draw>  # rolls a revealed draw again from its seed
```

## Requirements

PHP 8.5 or later with sodium, Symfony 8.2 with its Workflow component, Doctrine ORM 3.7, `phil/commerce-bundle` and
`tilttek/loot-tables`; the Form component, Symfony UX with the PHIL UX kit, the Console component and
`phil/paged-media` for the form, the components, the replay command and the production sheet.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
