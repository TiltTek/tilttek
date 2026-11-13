# Storing and publishing

`Tables` runs the tables of an application: it opens them, plays and takes back their moves through the engine,
keeps their logs in a `Contract\LogStore`, their states in a `Contract\StateCache`, and tells a
`Contract\TablePublisher` of every change.

| Method | What it does |
|---|---|
| `open()` | Opens a table of a game, by the game's name |
| `play()` | Makes a move, refused when the log holds another number of moves than the caller expected |
| `undo()` | Takes the last move back, refused with `GameOver` once the game is over |
| `state()` | The current state |
| `view()` | What a seat sees, shaped by its player's preferences, or the audience |
| `log()` | The whole log of a table, every move included |
| `finishedLog()` | The whole log of a table whose game is over, seed included, refused with `GameInProgress` while it goes on |
| `header()` | What the log holds besides its moves: seed, seats, options, number of moves and revision |
| `commitment()` | The commitment to the seed, read from the header |
| `reset()` | Lets go of the states it keeps, between two requests of a worker |

## The log store

A `LogStore` keeps the logs: `create()` a new one, `load()` a whole one, `header()` reads a `LogHeader` without the
moves, `moves()` gives the moves of a range window by window, as they are asked for, `append()` adds a move and
`removeLast()` takes the last one away.

The header carries the number of moves and the revision of the log. The revision counts every change — each move
appended, each move taken back — and never goes down, so two reads of one revision read the same moves. Its difference
with the number of moves, the lineage, changes only when a move is taken back.

Appending and removing take the header the caller read, and throw `ConcurrentMove` when the log holds another number of
moves or is at another revision. Of two moves made at once, one lands, and the other player reads the table again. A
move taken back and another made in its place leave the same number of moves but not the same revision, so a move
computed on the state from before is refused. A store on a database compares both in the statement that changes them,
inside the transaction that inserts the move.

`moves()` may give fewer moves than asked when the log changes while they are read; the caller reads the header again to
tell.

`Tables::play()` also takes the number of moves the player saw, `expected`, so that a move made on a stale screen is
refused rather than applied to a table that changed under it.

## The state cache

A `StateCache` spares a replay at each read. It keeps a `Snapshot` per table: the state, the revision of the log it
was built from and the commitment to the seed of its table.

| The cached snapshot | What `Tables` does |
|---|---|
| Reflects the header: same table, game, seed, revision and number of moves | Uses its state; no move is read |
| Precedes it: same table, game, seed and lineage, fewer moves | Applies the moves made since, read window by window |
| Anything else, or nothing | Replays the log from the setup, window by window |

A state built from moves since taken back, or from an earlier table of the same identifier, never matches the header,
so it is never served. `put()` keeps a snapshot unless the cache holds a later revision of the same table: a process
that read the log before a change does not write its stale state over the newer one. A cache without compare-and-set
may still let such a write land between two others; the next read sees that its revision is not the log's, rebuilds the
state and caches it in its place.

When the log changes while its moves are read, `Tables` reads the header again and builds the state anew, up to
`Tables::READS` times, then throws `ConcurrentMove`. A log that cannot be replayed while its header stays the same
throws `InvalidGame`.

`NullStateCache`, the default, replays at every read — a few microseconds a move —; `InMemoryStateCache` keeps the
snapshots in the process, for tests and scripts. A cache shared between processes keeps `Snapshot::toArray()` and reads
it back with `Snapshot::fromArray()` ([The log and replay](log-and-replay.md#the-state-as-plain-values)).

## One read per request

Every call of `Tables` reads the header of the log, one row. The states it built or read are kept, up to
`Tables::REMEMBERED`, the one read least recently let go first, and used again as long as the header says the log did
not change. The view of a seat, its state and the commitment to the seed then cost one read of the moves at most, and
none when the cache is up to date. A state kept is never trusted past a change of the log, so it stays safe when
another process plays meanwhile.

In a worker serving several requests, `reset()` lets go of the kept states between two of them; the bundle tags the
service for it. `InMemoryLogStore` and `InMemoryStateCache` also have `reset()`, for the tests they serve.

## Publishing

After every change, `Tables` publishes a `TableUpdate`: the table's identifier, the number of moves, the phase, the
round, the seats that may act now and whether the game is over. It holds identifiers and counters only, never the
game's data. A publisher on a Mercure hub publishes it on a topic of the table; each player's page then reads the view
of its seat, through an endpoint that checks who the player is. `NullTablePublisher`, the default, publishes nothing.
