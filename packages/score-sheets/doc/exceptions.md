# Exceptions

Every exception of the library implements `Tilttek\ScoreSheets\Exception\ScoreSheetsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidSheet` | A declaration does not hold together, or does not decode: a key, a label or a bound refused, a category that reads one declared after it, an operation out of its bounds, JSON too long or broken | `The category "fleet" reads "boats", which is not declared before it.` |
| `InvalidEntry` | A value, a participant, a team or an identifier is refused, or a result is too large to compute | `The category "boats" takes a value between 0 and 12.` |
| `IllegalTransition` | The status of the sheet refuses the change | `A closed sheet takes no score: reopen it first.` |
| `UnknownMatch` | The store holds no sheet for that match | `The store holds no sheet for the match "m-2031".` |
| `ConcurrentChange` | The sheet changed since the version the change was made on | `The sheet of the match "m-2031" is at version 4, not 3.` |
