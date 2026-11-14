# Writing a store

An application keeps the openings in its own storage by implementing `Contract\OpeningStore`:

| Method | Does |
|---|---|
| `find($calendar, $member, $day)` | The opening of that box by that member, or null |
| `add($opening)` | Keeps the opening unless one is there for the same calendar, member and day; true when kept |
| `undelivered($member)` | The member's openings not yet marked delivered, in every calendar, by calendar then day; an opening is added undelivered |
| `markDelivered($opening)` | Marks the opening of that calendar, member and day delivered; nothing when it is not there or delivered already |
| `openingsOf($calendar, $member)` | The member's openings of the calendar, by day |
| `eraseMember($member)` | Removes every opening of the member, in every calendar; how many were removed |

`add()` is the guard of idempotence: it must keep at most one opening per calendar, member and day even when two
requests write at once. In SQL, a unique key on the three columns and an insert that ignores a duplicate do it:

```sql
CREATE TABLE advent_opening (
    calendar VARCHAR(64) NOT NULL,
    member VARCHAR(320) NOT NULL,
    day SMALLINT NOT NULL,
    opened_at TIMESTAMP NOT NULL,
    delivered BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (calendar, member, day)
);

INSERT INTO advent_opening (calendar, member, day, opened_at) VALUES (?, ?, ?, ?) ON CONFLICT DO NOTHING;
UPDATE advent_opening SET delivered = TRUE WHERE calendar = ? AND member = ? AND day = ?;
```

The `delivered` column keeps whether the opening's `BoxOpened` was dispatched; a table written for 0.1 gains it with
`DEFAULT TRUE` for the rows already there, whose events were dispatched, then `DEFAULT FALSE` for the new ones.

The member is kept as `ResourceReference::toString()` and read back with `ResourceReference::fromString()`; the
moment keeps its offset. `Opening::toArray()` gives the four columns.

The library's tests hold `OpeningStoreTestCase`, the contract every store passes; copy it into the application's tests
and extend it with the store to check.
