# Storage

## The logs

`DbalLogStore` keeps the logs of the tables — the truth of every table — on a Doctrine DBAL connection, on MySQL,
PostgreSQL or SQLite:

| Table | One row per | Columns |
|---|---|---|
| `tilttek_game_engine_table` | table | `game_id`, `game`, `version`, `seed`, `seats`, `options`, `moves`, `revision` |
| `tilttek_game_engine_move` | move | `game_id`, `number`, `type`, `seat`, `payload`, `drew` |

The seats, the options and the payloads are JSON in text columns, written and read by the store, so that no database
reorders the keys of a mapping a game reads in order; the floats keep their fraction.

The row of a table is the header of its log: reading it costs one row, whatever the number of moves. `revision` counts
every change of the log, each move appended and each move taken back, and never goes down.

Appending a move changes the number of moves and the revision of its table in one statement that compares both with
those the caller read, then inserts the move, in one transaction. Of two moves made at once, the statement of the
second changes no row, and the move is refused with `ConcurrentMove`; so is a move computed before a move was taken
back and another made in its place, which left the same number of moves at a later revision. The primary key of the
moves keeps out a second move of one number whatever happens. Taking a move back lowers the number, raises the
revision the same way and deletes the move.

The moves are read window by window, `DbalLogStore::WINDOW` (256) at a time, as the library asks for them, so a replay
holds one window in memory rather than the whole log. A whole log, for its endpoint or its verification, is read back up
to the number of moves of its table, read again when its revision changed meanwhile, every field checked by the library,
a column that is not JSON refused with `InvalidGame`.

`DbalLogStore::delete()` forgets a table and its moves, when the application purges the tables it no longer keeps.

## The state cache

`CacheStateCache` keeps the current state of each table in a PSR-6 pool shared by every process — `cache.app`, or a
pool on Valkey or on Doctrine DBAL —, under a key hashed from the table's identifier. It keeps the JSON text of
`Snapshot::toArray()` — the state, the revision of the log it was built from and the commitment to the seed —, never a
PHP object, so that nothing the pool gives back is turned into one:

- a state whose data JSON would not give back as it is — an object in the game's data — is not cached at all, and every
  read of that table replays its log;
- whatever the pool gives back is read by `Snapshot::fromArray()`, and anything it refuses, or the state of another
  table, is forgotten;
- a snapshot is not written over one of a later revision of the same table, so a process that read the log before a
  change keeps its stale state to itself.

The library uses a cached state only when its revision and seed are those of the log, and brings an earlier one of the
same lineage forward with the moves made since; whatever the cache loses, the log rebuilds, a few microseconds a move.
PSR-6 has no compare-and-set. With `lock.factory`, the cache reads the snapshot it holds and writes the new one while
holding the lock of the key, for 5 seconds at most, and never waits for it: a writer that finds the lock held leaves the
cache as it is, so a stale state never lands over a later one, and a state left out is rebuilt by the next read.
Without a lock factory, a stale write may still land between the read and the write of another process; it is never
served, since its revision is not the log's, and the next read caches the state of the log in its place.

## One read per request

`GameTables` reads a table through the library's `Tables`, which reads the header of the log at each call and keeps the
states it built while that header stays the same. `GET /{table}` asks for the state, the view of a seat and the
commitment to the seed: one row of the header for each, and the moves at most once, none when the cache is up to date.
The service is tagged `kernel.reset`, so a worker lets go of the kept states between two requests; a state kept is never
used past a change of its log, even within one request.

Measured by `tests/Benchmark/LargeTableBench.php`, on a table of 8 000 moves of 16 000 bytes each, on SQLite in memory:
the view of a seat costs about 71 µs and a move about 0.2 ms on a cached state, and a replay from the setup about
0.43 s with a peak of 15 MB, the moves read 256 at a time rather than all at once.

## The lock

With `lock.factory`, each move and each move taken back is made while holding the lock of its table, named from a hash
of its identifier: the second of two moves made at once waits for the first to land, then plays against the table as
the first left it — refused as a concurrent move only when the player said which number of moves they saw. The lock's
time to live, 30 seconds unless configured, bounds how long a process that died keeps the others waiting.

Without a lock, the log store alone keeps two moves apart: the second is refused with `ConcurrentMove`, and the
player reads the table again.

## Opaque seat identifiers

A seat identifier is an identifier of the table, not of a person: `seat:3`, `north`, `red`, never `member:44` nor an
email address. The log keeps every seat for good, every move names its seat, and the log of a finished table, seed
included, may be shown to anyone who wants to verify its draws. The application keeps which member sits at which seat,
in a mapping of its own:

```php
$seats = ['seat:1', 'seat:2'];
$this->seating->assign($gameId, ['seat:1' => $ann->getId(), 'seat:2' => $bob->getId()]);
$tables->open('chess', $gameId, $seats);
```

The same opaque identifiers are all that leaves the log: a Mercure update carries the table, its counters and the seats
that may act; an exception names the table and the seat; a message the application dispatches about a table carries
these identifiers and nothing else, its player read from the mapping by its handler.

Erasing a member then means erasing that mapping: every log stays able to be replayed, and the bundle has nothing to
export or erase.

`GameTables::open()` refuses, with `IdentifyingSeat`, a seat that looks like it names a person:

| Refused | For instance |
|---|---|
| Anything with an `@`, raw or encoded as `%40` | `ann@example.com` |
| A dotted name ending in a common mail domain | `ann.example.com`, `jane.doe.gmail.com` |
| `member`, `user`, `account`, `customer`, `subscriber`, `profile`, `person`, `uid`, `login`, `email` or `mail`, in the singular or the plural, followed by a separator and anything, or by digits | `member:44`, `user-7`, `account_12`, `uid42` |

The guard catches the usual mistakes, not every way of naming a person: a seat drawn at random or numbered within the
table is the one that keeps the log free of personal data.
