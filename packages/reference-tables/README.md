# tilttek/reference-tables

**The tables a game master consults during play, found by key or by range, or rolled with their die — each with its
source, licence and page.**

tilttek/reference-tables holds the reference tables of a game system as a site declares them: the armour of a system
looked up by its key, the bonus of a level looked up by the range it falls in, a reaction table rolled on 2d6, a
wandering monster table rolled on a d100 where `96–00` ends the list. A table has columns — texts in several languages,
integers, or dice expressions such as the number appearing — and rows found either by their key or by the range of
values they cover, `≤2`, `3–5`, `12+`. A table with a die is rolled through `tilttek/dice`, with a modifier when the
system adds one, a total beyond the table taking its first or last row when the game says so; a die rolled at the table
is entered and finds its row the same way; the dice of a cell are rolled when the table asks for it. A search finds a
text in the names, descriptions, row keys and text cells of a catalogue, without regard to case or accents. Every table
carries its provenance — the title of its source, who holds it, its licence and the attribution the licence asks for,
from the closed list of `tilttek/rpg-systems` —, the game system it belongs to, a category to group it on a screen, and
the page where it is printed.

Tables are written in a YAML or JSON document that sets a source, a game system and a default language once and lets a
table state its own; every refusal names its place, `tables[2].rows[4].range`. A roll is told to an observer by the
table's key, the position of the row and the total, never by its text.

It is for an application that shows a game system's tables while people play — a game master's screen, a rules
reference, a virtual table, a study deck of a system's numbers. It does not draw content from weighted lists and
templates: `tilttek/generator` makes names, inns and treasures; a reference table gives back the row a value or a key
names, as the book prints it. It does not keep tables for anyone nor show them: the application holds its documents,
caches the catalogue and draws its pages.

It is a plain PHP library whose dependencies are `tilttek/rpg-systems`, which gives the provenances, licences and texts
by locale, `tilttek/dice`, which reads and rolls the dice, and `symfony/yaml`, which reads the YAML documents. Every
service keeps nothing between two calls and serves every request of a worker.

## Status

| Part | State |
|---|---|
| `ReferenceTable`, `Column`, `ColumnKind`, `Row`, `Cell`, `Lookup`: tables of up to 32 columns and 1,000 rows, found by key or by range, with their source, system, category and page | Implemented and tested |
| `Range`: `12`, `3–5`, `21+`, `≤0`, `96–00` on a d100, read, printed, without overlap, gaps listed | Implemented and tested, fuzzed |
| `TableCatalogue`: up to 2,000 tables, by system and category, with the sources a page shows | Implemented and tested |
| `Rolling\TableRoller`, `Rolling\TableRoll`: a table rolled with its die and a modifier, clamped when asked; a die entered; the dice of a cell rolled | Implemented and tested |
| `Search\TableSearch`, `Search\SearchHit`: a text found in names, descriptions, row keys and text cells, case and accents left aside | Implemented and tested |
| `Format\TableReader`, `Format\Syntax`: the YAML and JSON documents, each refusal at its place | Implemented and tested, fuzzed |
| `Contract\TableObserver`, `Event\TableRolled`: each roll told by identifiers and numbers | Implemented and tested |
| Exceptions under one `ReferenceTablesException` interface | Implemented and tested |
| Tables looked up by two keys at once, a row and a column | Not started |
| The Symfony bundle: the catalogue as a service, the screen's components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Tables](doc/tables.md) | Tables, columns, cells, rows, ranges, lookups, and the catalogue. |
| [The format](doc/format.md) | The YAML and JSON document of a site's tables, field by field. |
| [Rolling](doc/rolling.md) | Rolling a table, modifiers, clamping, dice entered at the table, cells rolled, and the observer. |
| [Searching](doc/searching.md) | What a search reads, how it matches, and its bounds. |
| [Extension points](doc/extension-points.md) | What an application gives the library, and where the library stops. |
| [Exceptions](doc/exceptions.md) | `ReferenceTablesException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, its fuzz targets and its benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/reference-tables
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\ReferenceTables\Rolling\TableRoller;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;

$tables = new TableReader()->load('examples/lanterns.yaml');

$tables->get('armor')->row('plate')->cell('defence')->toInt();          // 5
$tables->get('advancement')->lookup(7)->display('bonus');               // "3"

$roll = new TableRoller()->roll($tables->get('reaction'), new Randomizer(new Secure()), modifier: 1);
$roll->row->display('attitude', 'fr');                                  // "Amical", on a total of 9 to 11

echo new AttributionRenderer()->html($tables->provenances(['reaction']));
```

The document is [`examples/lanterns.yaml`](examples/lanterns.yaml): four small tables written for the examples.

## Requirements

PHP 8.5 or later, with `mbstring`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The
licence covers the library's code; the material of a table keeps the licence its provenance states.
