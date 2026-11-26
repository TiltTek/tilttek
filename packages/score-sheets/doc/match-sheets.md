# Match sheets

## The life of a sheet

`ScoreKeeper` runs the sheets of a store, one per match, and keeps nothing between two calls.

| Method | From | To |
|---|---|---|
| `prepare(MatchId, SheetDefinition, Entries)` | — | Prepared: hidden from the players |
| `publish(MatchId, int $version)` | Prepared | Published: shown, open to entries |
| `record(MatchId, int $version, ParticipantId, string $category, ?int $value)` | Prepared or Published | The same; null erases the value |
| `close(MatchId, int $version)` | Published | Closed: the result passed on |
| `reopen(MatchId, int $version)` | Closed | Published again, to correct it |

A prepared sheet takes values entered ahead — handicaps, a score carried from a first round — while the players do
not see it; `MatchSheet::isVisible()` says whether they may. A closed sheet takes no value until it is reopened. A
change from any other status is refused with `IllegalTransition`.

`record()` returns the scoreboard computed again; `scoreboard()` computes it at any time, `sheet()` returns the
stored sheet.

## A score entered too early

A sheet closed before a score was right is reopened, corrected and closed again. Each closing counts one more
revision: the first result is revision 1, the corrected one revision 2, and `MatchResult::isCorrection()` says that it
replaces the one before ([Passing results on](results.md)).

## Versions

A `MatchSheet` has a version, one more at every change. A change is made against the version the scorer saw, and
`ConcurrentChange` refuses it when another change was recorded first: two scorers who correct the same value at once
get one change and one refusal.

## The store port

`Contract\MatchSheetStore` has two methods: `find(MatchId): ?MatchSheet`, and `save(MatchSheet)`, which writes the
sheet only over the version before it — none for version 0 — and throws `ConcurrentChange` otherwise. The sheet holds
the declaration, the entries, the status, the revision and the version; the scores are never stored, they are
computed.

## A store of your own

A store on a database keeps one row per match, with `SheetDefinition::toArray()` and `Entries::toArray()` as JSON,
the status, the revision, and the version in a column that the update checks:

```sql
UPDATE match_sheet SET sheet = :sheet, entries = :entries, status = :status, revision = :revision, version = :version
WHERE id = :id AND version = :version - 1
```

No row updated means `ConcurrentChange`. It reads the row back with `SheetDefinition::fromArray()` and
`Entries::fromArray()`, which checks every value against the sheet again.

`Store\InMemoryMatchSheetStore` is the store for tests.
