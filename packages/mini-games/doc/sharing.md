# Sharing a result

A `ShareGrid` is a result to paste anywhere: rows of cells and a summary.

```text
Bulls and Cows 12 4/10

🟨🟨⬛⬛
🟩🟨🟨⬛
🟩🟩🟩⬛
🟩🟩🟩🟩
```

## The cells

| `Cell` | Means |
|---|---|
| `Hit` | Right, and in the right place |
| `Near` | Right, but in another place |
| `Miss` | Not right |
| `Empty` | Not played |

A grid holds up to 32 rows of up to 16 cells, and a summary of up to 32 printable ASCII characters: `4/10`, `X/10`.

## What a grid never tells

A grid tells how each step went, never what it was. A game builds it so that nobody who has not played can work out
the answer from it: Bulls and Cows writes the bulls of a guess before its cows, so that the places stay hidden; Higher
or Lower writes whether a call was right, never whether it was higher or lower, which would give the numbers away.

## Writing a grid

`toText($title, $symbols)` writes a title line — the title the application writes in its language, then the summary
—, a blank line, and one line per row. The title's runs of spaces and line breaks become one space.

| `CellSymbols` | Hit, near, miss, empty |
|---|---|
| `CellSymbols::squares()`, the default | 🟩 🟨 ⬛ ⬜ |
| `CellSymbols::plain()` | `#` `+` `.` `-` |
| `new CellSymbols(…)` | Four symbols of 1 to 16 bytes of UTF-8 each, on one line |

The plain symbols suit a screen reader, which reads the coloured squares one name at a time, and a font without them.
An application may offer both, and describe the grid in words next to it.
