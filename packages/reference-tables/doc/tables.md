# Tables

## A table

A `ReferenceTable` is one table of a game system as a game master reads it:

| Field | What it holds |
|---|---|
| `key` | The table's key in its catalogue, `reaction`: lowercase letters, digits, `_`, `-` and single dots, 96 characters at most. |
| `names` | Its name by locale, a `Translations` of `tilttek/rpg-systems`; `name($locale)` reads it with the fallbacks of `Translations::in()`. |
| `provenance` | Its source: title, holder, address, licence and attribution, a `Provenance` of `tilttek/rpg-systems`. |
| `columns` | 1 to 32 `Column`s, each key once. |
| `lookup` | `Lookup::Key` or `Lookup::Range`: how its rows are found. |
| `rows` | 1 to 1,000 `Row`s, in the order the table prints them. |
| `die` | The die a table looked up by range is rolled with, one instruction of `tilttek/dice` kept in its canonical notation: `d%` becomes `1d100`, `d20 adv` becomes `2d20kh1`. Null for a table that is only consulted. |
| `system` | The key of the game system the table belongs to, `lanterns`; optional. |
| `category` | A key that groups tables on a screen, `combat`, `social`; optional. |
| `page` | Where the source prints it, `p. 254`: one line of up to 200 characters; optional. |
| `description` | What the table is for, by locale; optional. |

A table that breaks a rule is refused with `InvalidTable` when it is built: a die on a table looked up by key, a die of
two instructions, a row of the wrong kind, a cell of a column the table does not have or of another kind than its
column, a key twice, two ranges that overlap.

## Columns and cells

A `Column` has a key, which starts with a letter and is never `key` nor `range` — the names a document gives the row's
own fields —, a heading by locale, and a `ColumnKind`:

| Kind | A cell holds | Read with |
|---|---|---|
| `Text` | A text by locale, up to 4,000 characters and 64 locales. | `translations()`, `display($locale)` |
| `Number` | An integer from −1,000,000,000 to 1,000,000,000. | `toInt()`, `display()` |
| `Dice` | A dice expression of `tilttek/dice`, kept in its canonical notation: `d4+1` becomes `1d4+1`. | `notation()`, `pool()`, `display()` |

`Cell::text()`, `Cell::number()` and `Cell::dice()` make them; reading a cell as another kind raises `LookupRefused`.
A row may leave a cell out: `Row::has()` says whether it holds one, `Row::cell()` raises `UnknownTable` for a missing
cell, and `Row::display()` gives an empty string.

## Rows, keys and ranges

A `Row` has either a key, in a table looked up by key, or a `Range`, in a table looked up by range — never both — and
its cells by column key. `Row::keyed()` and `Row::ranged()` make them; `label()` gives the key, or the range as a table
prints it.

A `Range` covers its two ends and every integer between them, within ±1,000,000:

| Written | Covers | Printed |
|---|---|---|
| `12` | 12 | `12` |
| `3-5`, `3–5`, `3—5`, `3 – 5` | 3 to 5 | `3–5` |
| `-3--1` | −3 to −1 | `-3–-1` |
| `21+` | 21 and above | `21+` |
| `≤0`, `<= 0` | 0 and below | `≤0` |
| `96-00` on a d100 | 96 to 100 | `96–100` |

`Range::parse()` reads a range of up to 32 bytes; its second argument says the table rolls a d100, on which `00` stands
for 100 — on any other die, `00` is 0. `new Range($min, $max)` and `Range::of($value)` build one in code;
`contains()` and `overlaps()` compare them.

## Lookups

| Lookup | Call | Gives | Refuses |
|---|---|---|---|
| By key | `row('plate')` | The row of the key. | `UnknownRow` when no row has it; `LookupRefused` on a table looked up by range. |
| By key | `hasRow('plate')` | Whether a row has the key; false on a table looked up by range. | |
| By range | `lookup(7)` | The row whose range covers the value. | `UnknownRow` when none does; `LookupRefused` on a table looked up by key. |
| By range | `position(7)` | The row's position in `$rows`, from 0, or null. | `LookupRefused` on a table looked up by key. |
| By range | `bounds()` | The lowest and the highest value the ranges cover. | `LookupRefused` on a table looked up by key. |
| By range | `gaps()` | The values between the lowest and the highest range that no row covers, as ranges in order. | `LookupRefused` on a table looked up by key. |

A lookup by key reads an index; a lookup by range searches the ranges, sorted once when the table is built, by
halving: a table of 1,000 rows answers in ten comparisons. The rows are given in any order; `rows` keeps that order.

A table of levels that skips a level on purpose and a rolled table with a hole by mistake look alike: `gaps()` lists the
holes, for a check when the tables are written, and a roll that falls in one is refused.

## The catalogue

A `TableCatalogue` holds up to 2,000 tables, each key once, in the order they were given:

- `get()` and `has()` find a table by key, `get()` raising `UnknownTable`;
- `tables()` gives them, those of one system or one category when asked;
- `categories()` gives the categories, each once, in the order they first appear, of one system when asked;
- `with()` gives a catalogue with more tables after its own, from the documents of several sources;
- `provenances()` gives the sources of every table, or of the tables a page shows, each once: the
  `AttributionRenderer` of `tilttek/rpg-systems` turns them into the notices the page displays.

```php
use Tilttek\RpgSystems\Attribution\AttributionRenderer;

$notices = new AttributionRenderer()->html($catalogue->provenances(['reaction', 'armor']));
```
