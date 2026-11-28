# The format

A site declares its tables in documents of the format `tilttek-reference-tables/1`, written in YAML or in JSON.
`Format\TableReader` reads them:

| Call | Reads |
|---|---|
| `load($path)` | A file, `.yaml`, `.yml` or `.json`, its size checked before it is read. |
| `parse($text, $syntax)` | A text, in the `Syntax::Yaml` or `Syntax::Json` given. |
| `decode($text, $syntax)` | A text decoded to an array, not yet read as tables. |
| `catalogue($document)` | A decoded array. |

A document is at most 2 MiB, or the size given to the reader's constructor. YAML is read by `symfony/yaml` with objects
and other PHP types refused; JSON is read to a depth of 64 at most.

## The document

```yaml
format: tilttek-reference-tables/1
locale: en
system: lanterns
source:
  title: Lanterns Quick Reference
  licence: CC0-1.0
  author: The Lanterns writers
tables:
  - key: reaction
    names: {en: Reaction, fr: Réaction}
    category: social
    page: p. 12
    die: 2d6
    columns:
      - {key: attitude, names: {en: Attitude, fr: Attitude}}
    rows:
      - {range: "≤2", attitude: {en: Hostile, fr: Hostile}}
      - {range: 3-11, attitude: {en: Uncertain, fr: Incertain}}
      - {range: 12+, attitude: {en: Helpful, fr: Serviable}}
```

| Field | Required | What it holds |
|---|---|---|
| `format` | yes | `tilttek-reference-tables/1`. |
| `locale` | no | The locale of a text written as a plain string; `en` when left out. |
| `system` | no | The key of the game system of every table that names none. |
| `source` | no | The source of every table that names none. |
| `tables` | yes | 1 to 2,000 tables. |

## A table

| Field | Required | What it holds |
|---|---|---|
| `key` | yes | The table's key. |
| `names` | yes | A text, or texts by locale. |
| `description` | no | A text, or texts by locale. |
| `system` | no | The key of its game system, in place of the document's. |
| `category` | no | The key of its category. |
| `page` | no | Where the source prints it; a number is read as its text. |
| `source` | no | Its source, in place of the document's; one of the two is required. |
| `lookup` | no | `key` or `range`; `range` when left out. |
| `die` | no | The die of a table looked up by range: `2d6`, `d%`, `1d20+1`. |
| `columns` | yes | 1 to 32 columns. |
| `rows` | yes | 1 to 1,000 rows. |

A column has a `key`, `names` — a text or texts by locale — and a `kind`, `text`, `number` or `dice`; `text` when left
out.

A row has a `key`, in a table looked up by key, or a `range`, in a table looked up by range, and one field for each
cell it holds, named by its column's key:

| Kind | Written as |
|---|---|
| `text` | A text, or texts by locale; a number is read as its text. |
| `number` | A whole number. |
| `dice` | A dice expression; a number is read as its text, a constant. |

A range is written as [Tables](tables.md#rows-keys-and-ranges) lists. When the table's die is a d100, `00` stands for
100. In YAML, quote a range that starts with `≤`, `<` or `-`, so that it is read as a text.

## A source

| Field | Required | What it holds |
|---|---|---|
| `title` | yes | The title of the source. |
| `licence` | yes | One of `CC-BY-4.0`, `CC-BY-3.0`, `CC-BY-SA-4.0`, `CC0-1.0`, `ORC` and `owned`, the closed list of `tilttek/rpg-systems`. |
| `attribution` | for a licence that asks for one | The statement the page shows. |
| `author` | no | Who holds the source. |
| `url` | no | Where it is published, an absolute http or https address. |

## Refusals

Every refusal is an `InvalidDocument` whose message starts with the place of the field, then what is wrong there:

```text
tables[0].rows[3].range: the range 5–3 runs backwards.
tables[1]: the rows "1–3" and "3–4" of the table "wandering" overlap.
source.licence: the licence is one of CC-BY-4.0, CC-BY-3.0, CC-BY-SA-4.0, CC0-1.0, ORC, owned.
```

A field the format does not name is refused, at every level: a misspelt `colums` is an error, not a table without
columns. A refusal of a table, a column, a row, a cell or a source keeps the exception it replaces as its previous one.
