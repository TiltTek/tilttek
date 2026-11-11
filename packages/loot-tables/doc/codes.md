# Codes

## Shop codes

`ShopCode` writes the faces of a result as a shop writes them in its codes: one character per die, in the order of
the tables. The digits 1 to 9 stand for themselves, then the hexadecimal goes on past `F` to `K`:

| Faces | 1–9 | 10 | 11 | 12 | 13–16 | 17–20 |
|---|---|---|---|---|---|---|
| Character | `1`–`9` | `A` | `B` | `C` | `D`–`G` | `H`–`K` |

A d10's highest face is `A`, a d12's `C`, a d20's `K`. Six d6 showing `3 5 2 6 1 4` write `352614`.

- `ShopCode::supports($box)`: whether every die of the box has 20 sides or fewer.
- `ShopCode::write($result)`: the code; `InvalidCode` for a box with a larger die.
- `ShopCode::read($box, $code, $source)`: the result of a code typed at a register, in upper or lower case, one
  character per die; the source is `Physical` unless given. `InvalidCode` refuses a code of another length, a
  character that is not a face of its die, or a box without a code.

## Draw texts

`DrawText` writes a result as one line a receipt shows and a program reads back: the faces joined by `-`, a colon and
a space, then the names of the entries joined by commas.

```text
3-5-2-6-1-4: Sword, Helmet, Boots, Shield, Ring, Cloak
```

- `DrawText::write($result)`: the line.
- `DrawText::read($box, $text, $source)`: the result of the line, read from its faces alone, so that an entry renamed
  since still reads. `InvalidCode` refuses a line that does not start with one face per die, each a face of its die.
- `DrawText::longest($box)` and `DrawText::fits($box)`: the longest line a draw of the box may write, and whether it
  is within `DrawText::MAX_LENGTH`, 1000 characters.
