# Writing a store

The library keeps its state behind five interfaces; the in-memory implementations serve tests and trials, and
`tilttek/collections-bundle` provides those on Doctrine DBAL.

| Interface | Keeps | Must |
|---|---|---|
| `Store\CopyStore` | The copies, by identifier | Write a copy once even when two processes write it; reassign a copy only from the owner named, marking it moved; take a copy back only from its member and if never moved; forget the copies of a source from a moment, unless moved |
| `Store\CompletionStore` | The sets completed | Write a set once per member |
| `Pack\OpeningStore` | The packs opened, by key | Write an opening once per key |
| `Dice\ChoiceStore` | The dice chosen | Keep one choice per member and number of sides |
| `Daily\DailyStore` | The calendar, by day | Keep one pick per day; read the picks between two days in order |

Every list is returned in a stable order: copies by moment then identifier, completions and openings by moment, choices
by number of sides, picks by day.

The tests of the library hold one abstract test case per contract — `CopyStoreTestCase`, `CompletionStoreTestCase`,
`OpeningStoreTestCase`, `ChoiceStoreTestCase`, `DailyStoreTestCase` —: extend it with a method that returns a fresh
store, and your store passes the same checks as the library's.
