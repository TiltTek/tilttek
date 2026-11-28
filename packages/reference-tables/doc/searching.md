# Searching

`Search\TableSearch` finds a text in the tables of a catalogue, in one locale:

```php
use Tilttek\ReferenceTables\Search\TableSearch;

$hits = new TableSearch()->search($catalogue, 'fantome', 'fr', limit: 20, system: 'lanterns');

$hits[0]->table;     // "wandering"
$hits[0]->position;  // 3, the row's position in the table's $rows
$hits[0]->column;    // "encounter"
$hits[0]->text;      // "Le fantôme à la lanterne"
```

## What it reads

Table by table, in the catalogue's order — or the tables of one system —, it reads:

1. the table's name in the locale;
2. its description in the locale;
3. for each row in order, its key, then its text cells in the order of the columns, in the locale.

A number or a dice cell is not read. A text in the locale falls back as `Translations::in()` does: the region's language,
then English, then the first text held.

A `SearchHit` gives the table's key, the row's position — null for a name or a description —, the column — null for a
name, a description or a row's key — and the text that matched.

## How it matches

The query, spaces around it left out, matches a text that contains it, without regard to case — `mb_strtolower()` — nor
to the accents of Latin letters: `é`, `è`, `ê` and `ë` are read as `e`, `ç` as `c`, `œ` as `oe`, `ß` as `ss`. Letters of
other scripts are compared as written, in lowercase.

## Bounds

A query is 1 to 100 characters of UTF-8, and a search gives 1 to 100 hits, 20 when not asked; it stops at its limit.
Anything else raises `InvalidTable`.

The search reads the catalogue from start to end, which suits the tables of a site: 50 tables of 100 rows in two
languages are read in about ten milliseconds. An application that searches a larger corpus, or the rules of a system
with its tables, gives the same texts to a search engine and keeps the hits' identifiers.
