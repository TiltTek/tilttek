# Production sheet

## What is printed

`LootPrint` composes what is printed of an order's draws, read from `LootResults`:

```php
foreach ($print->compose($order->number) as $printed) {
    $printed->draw;      // the LootDraw: its line, its option and its unit
    $printed->state;     // PrintState::Verified, NotVerified, Physical or Pending
    $printed->picks();   // the entry of each table, with its face, its image and its slot
    $printed->text();    // the text drawn, for a text
    $printed->shopCode;  // the shop code, when its box has one
    $printed->images();  // the images of the entries, in the order of the tables, for the print files of the unit
}
```

| `PrintState` | The draw |
|---|---|
| `Verified` | is revealed, and its proof checks out against the commitment shown before the payment |
| `NotVerified` | is revealed, and its proof does not check out |
| `Physical` | is physical dice entered at a register |
| `Pending` | is not drawn yet: it has no result, no shop code and nothing to print |

An application that sends print files to a workshop reads the images of each unit from it; the production sheet
writes it out.

## The sheet

`LootSheetPdf` prints the composition of an order for whoever makes the order, through `phil/paged-media`. The service
is registered when that package is installed.

```php
$pdf = $sheet->render($order->number, $clock->now());
```

For each draw, by line, option and unit:

- the entry of each table, with its slot and its face — or, for a text, the text drawn;
- its shop code;
- whether it is verified against its commitment, entered from physical dice at a register, or does not check out;
- `Not drawn yet` for a draw not revealed nor entered.

An order without draws prints a sentence that says so. A sheet longer than a page goes on over the next ones.

| Argument | What it sets |
|---|---|
| `labels` | The words of the sheet, by the keys of `LootSheetPdf::LABELS`, translated; English by default |
| `size` | The page; Letter by default |
| `font`, `bold` | The fonts; Helvetica prints the Western languages, a `TrueTypeFont` any other script |

An application printing in another language registers the service again with its labels:

```yaml
services:
    Tilttek\LootTablesBundle\Infrastructure\Pdf\LootSheetPdf:
        arguments:
            $print: '@Tilttek\LootTablesBundle\Application\LootPrint'
            $labels: { title: 'Butin de la commande %s', pending: 'Pas encore tiré' }
```
