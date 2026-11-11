# Limits

`Limits` bounds what an expression may ask for, against abuse. What goes past a limit is refused with `LimitExceeded`,
never truncated.

| Limit | Default | Checked |
|---|---|---|
| `dice` | 100 | The dice a pool rolls or lists and the items it draws from text lists, before rerolls and explosions, when it is parsed and when it is rolled — every branch counted when parsed, the branch that holds when rolled, what `repeat()` repeats once for each time; a count rolled first is counted once rolled |
| `sides` | 1000 | The sides of each numbered die and the faces of a die on a range, at the same times; sides rolled first once rolled |
| `explosions` | 100 | The explosions of a roll, every kind and the dice of dice all the same, across the whole pool, as they happen |
| `expressions` | 10 | The instructions of a pool |
| `length` | 256 | The characters of an input, before it is parsed |
| `rerolls` | 100 | The rerolls of a roll — repeated, once or unique —, across the whole pool, as they happen |
| `repetitions` | 100 | The repetitions of `repeat()`, across the whole pool — those within `repeat()` once for each time —, when it is parsed and as they happen; a count rolled first is counted once rolled |
| `text` | 4096 | The characters of a text a sentence gives, once its placeholders are replaced |

Each limit is at least 1. A count that goes past the range of an integer is taken as the largest integer, and refused.
Pass the same `Limits` to the parser and the roller — `DiceRoller` does — so that a pool built in code is held to the
same bounds as one typed by a person.
