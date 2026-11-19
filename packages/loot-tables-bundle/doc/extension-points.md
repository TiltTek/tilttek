# Extension points

## The catalogue of the commerce core

The application's `SellableOptions` declares the options of each product: a draw option naming
`Drawers::box('<key>')`, or a text option drawn among its entries naming `Drawers::TEXT`. The bundle reads the entries
of a text through the commerce core's `LineOptions`.

## When the draws are committed

`CommitOnPlace` commits the draws when the order workflow places the order. An application that places orders
otherwise sets `commit_on_place: false` and calls `LootCommitments::commit()` once the order is placed, before the
buyer pays.

## The screens

Override the templates as for any bundle, under `templates/bundles/TilttekLootTablesBundle/`:

| Template | What it draws |
|---|---|
| `components/Commitments.html.twig` | The buyer's draws, their commitments, and once revealed their seeds and entries |
| `components/PhysicalDice.html.twig` | The form of the physical dice |

The words are in the `tilttek_loot_tables` domain; add a file `translations/tilttek_loot_tables.<locale>.yaml` for
another language, with the keys of the bundle's files. The form of the dice may be rendered with any form theme. The
buyer's draws are drawn with the recipes `Card` and `Badge` of the UX kit the application installed; restyle them
there.

## What is printed

`LootPrint` composes what is printed of each draw; an application that sends print files to a workshop reads the
images of each unit from it.

## The production sheet

`LootSheetPdf` takes its labels, its page and its fonts as arguments: register the service again to change them, as
[the production sheet](production-sheet.md) shows.

## Where the draws are kept

`LootDrawRepository` is an interface: the bundle gives a Doctrine store and a store in memory. An application with
another store aliases the interface to its own service.

## The randomness

The seeds come from `SecureSeeds`, which takes `Random\Engine\Secure` and nothing else: PHP declares that engine
final, so no other source of randomness can stand in for the operating system's cryptographic generator.
