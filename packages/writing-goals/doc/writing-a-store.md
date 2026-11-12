# Writing a store

An application keeps the goals and the counts in its own storage by implementing two contracts.

## `Contract\GoalStore`

| Method | Does |
|---|---|
| `save($goal)` | Keeps the goal, replacing the one of the same identifier |
| `find($id)` | The goal, or null |
| `goalsOf($author)` | The author's goals, by identifier |
| `remove($id)` | Removes the goal; nothing when there is none |

A goal is rebuilt with its constructor from its columns: the identifier, the author as
`ResourceReference::toString()`, the target, the two days as `Day::toString()`, the unit's and curve shape's values,
the custom curve's `points`, the writing days' `weekdays` and `excluded()`, and the timezone's name; `Curve::of()` reads
a curve back from its shape and points.

## `Contract\LogStore`

| Method | Does |
|---|---|
| `progress($goal)` | The goal's counts, as a `Progress` — `Progress::fromArray()` reads them by date |
| `add($goal, $day, $delta)` | Adds to a day's count in one step, never below 0; the day's count after |
| `set($goal, $day, $count)` | Replaces a day's count |
| `forget($goal)` | Removes every count of the goal |

`add()` must not lose a count when two requests add at once. In SQL, one row per goal and day, and an upsert that adds:

```sql
CREATE TABLE writing_log (
    goal VARCHAR(128) NOT NULL,
    day DATE NOT NULL,
    count INTEGER NOT NULL,
    PRIMARY KEY (goal, day)
);

INSERT INTO writing_log (goal, day, count) VALUES (?, ?, LEAST(1000000000, GREATEST(0, ?)))
ON CONFLICT (goal, day) DO UPDATE SET count = LEAST(1000000000, GREATEST(0, writing_log.count + EXCLUDED.count)) RETURNING count;
```

The library's tests hold `GoalStoreTestCase` and `LogStoreTestCase`, the contracts every store passes; copy them into
the application's tests and extend them with the stores to check.
