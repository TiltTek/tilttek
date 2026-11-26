# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `SheetDefinition` | The sheets of its games, declared as data | [Declaring a sheet](declaring-sheets.md) |
| `Contract\MatchSheetStore` | The store of its match sheets, on its own database | [Match sheets](match-sheets.md#a-store-of-your-own) |
| `Message\MatchResultRecorded` | The handler that passes a result on to its play log or its rating | [Passing results on](results.md) |
| The layout of a sheet | The HTML or PDF of a `BlankSheet` or a `Scoreboard` | [Printing a blank sheet](printing.md) |

The declarations, entries, scoreboards and results are values; the operations are a closed set the codec reads.
`ScoreKeeper` and `Scorer` keep no state between two calls.
