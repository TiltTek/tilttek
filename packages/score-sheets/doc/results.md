# Passing results on

`ScoreKeeper::close()` returns a `MatchResult`: neutral data that a play log, a rating or a leaderboard reads without
the library depending on any of them.

| Field | Meaning |
|---|---|
| `match` | The match |
| `game` | The key of the game |
| `revision` | 1 for the first result, 2 and more for a correction after the sheet was reopened |
| `participants` | A `Placement` by participant, in rank order: identifier, rank, total |
| `teams` | A `Placement` by team, in rank order: identifier, rank, total, members |
| `complete` | Whether every value was entered |

```php
$result = $keeper->close($match, $version);

$bus->dispatch($result->message());   // Message\MatchResultRecorded: the match and the revision only
```

`Message\MatchResultRecorded` carries identifiers only; its handler reads the sheet from the store and computes the
result again with `MatchResult::of($sheet, $scorer->score($sheet->sheet, $sheet->entries))`. A handler that receives
a revision it has already recorded, or an older one, ignores it; a newer revision replaces the result it recorded.

A rating takes the places as a suggestion: the ranks say who finished where, and the rating decides what it does with
ties, incomplete sheets and teams.
