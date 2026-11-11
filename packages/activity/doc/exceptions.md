# Exceptions

Every exception of the library implements `Tilttek\Activity\Exception\ActivityException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidActivity` | A verb, a pattern, a key, a context, a quantity, a moment outside the years 1970 to 9999 or the journal's window, a position, a read or a batch size is refused | `"Game.played" is not a verb: lower-case segments of letters, digits and "_", each starting with a letter, joined by dots, such as "game.played".` |
| `IdempotencyConflict` | A key already recorded is recorded again for another gesture | `The idempotency key "k" is already recorded for another gesture.` |
| `UnknownVerb` | The verbs are declared and the activity's verb is not one of them | `The verb "news.read" is not declared.` |
| `UnknownProjection` | A projection is asked for by a name none has | `No projection is named "quests".` |
| `InvalidProjection` | A projection name is not one, or two projections share it | `Two projections are named "points".` |
