# Tables and boxes

## Entries

A `LootEntry` is one thing a table may give:

| Property | Contract |
|---|---|
| `key` | 1 to 64 lowercase letters, digits, `_` or `-`, starting with a letter or a digit |
| `name` | 1 to 1000 characters of UTF-8, not blank: what a receipt shows, or the whole text of an entry of a list |
| `image` | the path or URL of its picture, 1 to 2048 visible ASCII characters, or `null` |
| `slot` | where it goes on what is made of it — `head`, `left-hand`, `belt` —, a key, or `null` |

## Tables

A `LootTable` is rolled with one die: it has as many entries as the die has sides, from 2 to 1000, the entry of face
1 first. Six entries roll a d6, ten a d10, twenty a d20. Its key follows the rule of an entry's, its name is 1 to 200
characters, and each entry key appears once.

- `sides()`: the sides of its die.
- `entry($face)`: the entry of a face, `InvalidLoot` for a face the die does not have.
- `LootTable::ofTexts($key, $name, $texts)`: a table of texts, each an entry keyed by its face — the list a text is
  drawn from, as an epitaph among those a product offers.

## Boxes

A `LootBox` is what a product declares it rolls: 1 to 10 tables, in order, each rolled once with its own die. Six
tables of six entries roll 6d6; a d20 table and two d8 tables roll `1d20, 1d8, 1d8`. A table may come back more than
once. Ten is the number of instructions `tilttek/dice` rolls together by default.

- `dice()`: the sides of each die, in the order of the tables.
- `pool()`: the dice as `tilttek/dice` rolls them, one instruction per table.
- `LootBox::single($table)`: a box of one table, for a text drawn from a list.

## Catalogues

A `LootCatalog` holds the tables and boxes of an application by key. It refuses a key listed twice, a box that rolls a
table it does not hold, and a box whose draws `DrawText` could not write within 1000 characters: the faces, the
separators and the longest name of each table count. `box()` and `table()` throw `UnknownLoot` for a key they do not
know; `hasBox()`, `boxes()` and `tables()` list them in the order they were given.

`CatalogReader::read()` builds one from plain arrays, as a decoded YAML or JSON document gives them:

```yaml
tables:
    headgear:
        name: Headgear
        entries:
            - { key: helmet, name: Helmet, image: /loot/helmet.png, slot: head }
            - { key: hood, name: Hood, slot: head }
boxes:
    starter:
        name: Starter box
        tables: [headgear, headgear]
```

A value it cannot read is refused with `InvalidLoot`, which names its path: `tables.headgear.entries.0.key is a text.`
A numeric key is read as its text.
