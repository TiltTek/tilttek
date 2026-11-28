# Exceptions

Every exception of the library implements `Exception\ReferenceTablesException`: catch it to catch them all.

| Exception | Extends | Raised when |
|---|---|---|
| `InvalidTable` | `InvalidArgumentException` | A table, column, row, cell or range breaks a rule; a modifier, a value entered, a query or a limit is out of its bounds. |
| `InvalidDocument` | `InvalidArgumentException` | A document cannot be read; the message starts with the place of the field. |
| `UnknownTable` | `OutOfBoundsException` | A catalogue holds no table of the key, a table no column, or a row no cell in the column. |
| `UnknownRow` | `OutOfBoundsException` | A table holds no row of the key, or no range covers the value. |
| `LookupRefused` | `LogicException` | A key is asked of a table looked up by range, a value of one looked up by key, a table without a die is rolled, a cell is read as another kind, or a die gives no number. |

Some messages:

```text
The table "armor" has no row "chain".
No row of the table "reaction" covers 13.
The table "reaction" is looked up by range, not by key.
The table "weather" has no die to roll.
The rows "1–5" and "5–6" of the table "wandering" overlap.
"high" is not a range such as "12", "3–5", "21+" or "≤0".
tables[0].rows[2].value: a whole number is expected.
```

A die or a cell rolled beyond the limits of the `DiceRoller` raises the `DiceException` of `tilttek/dice`.
