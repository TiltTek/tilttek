# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Tables

| Type | Role |
|---|---|
| `ReferenceTable` | A table: columns, rows found by key or by range, die, source, system, category and page. |
| `Column` | A column: key, heading by locale and kind. |
| `ColumnKind` | Text, number or dice. |
| `Cell` | One value of a row: a text by locale, an integer or a dice expression. |
| `Row` | A row: its key or its range, and its cells by column. |
| `Lookup` | By key or by range. |
| `Range` | The values a row covers, both ends included, read and printed. |
| `TableCatalogue` | The tables of a site, by key, system and category, with their sources. |

## Rolling

| Type | Role |
|---|---|
| `Rolling\TableRoller` | The service: rolls a table with its die and a modifier, enters a die rolled at the table, rolls a cell. |
| `Rolling\TableRoll` | A roll: the die's value, the modifier, the total, the row, its position and the dice kept. |
| `Contract\TableObserver` | Told of each roll. |
| `Event\TableRolled` | A roll, by the table's key, the row's position, the total and whether it was entered. |

## Searching

| Type | Role |
|---|---|
| `Search\TableSearch` | The service: finds a text in names, descriptions, row keys and text cells. |
| `Search\SearchHit` | Where a text was found: table, row position, column and text. |

## Reading documents

| Type | Role |
|---|---|
| `Format\TableReader` | The service: reads a YAML or JSON document into a `TableCatalogue`. |
| `Format\Syntax` | YAML or JSON, and the syntax of a file by its extension. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\ReferenceTablesException` | The interface of every exception of the library. |
| `Exception\InvalidTable` | A value that breaks a rule. |
| `Exception\InvalidDocument` | A document that cannot be read, at its place. |
| `Exception\UnknownTable` | A table, a column or a cell that is not there. |
| `Exception\UnknownRow` | A key or a value no row answers. |
| `Exception\LookupRefused` | A lookup or a roll the table does not allow. |

## Internal

| Type | Role |
|---|---|
| `Internal\Check` | The checks of keys and labels. Internal. |
| `Internal\Fields` | One mapping of a document, read field by field. Internal. |
| `Internal\Fold` | A text folded for a search. Internal. |
