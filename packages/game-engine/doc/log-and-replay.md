# The log and replay

## The log is the truth

A `GameLog` holds everything a table is: the identifier of the table, the game and the version of its rules, the seed,
the seats, the options chosen, and the moves made, numbered from 1, each with whether it drew at random. It holds at
most 100 000 moves. `toArray()` writes it as plain values, which JSON stores, and `fromArray()` reads them back,
refusing a field of the wrong type.

The state, a `GameState`, is only a cache: the phase, the turn, the initiative, the game's data and its outcome after
the moves of the log. Nothing in it is kept that the log cannot rebuild.

## Chance from the seed

Every step of a table draws its chance from a `Randomness` seeded by the table's seed and the step's place in the log
— 0 for the setup, then the number of the move —, through PHP's `Random\Engine\Xoshiro256StarStar`. Replaying a move
therefore draws the same values, and a move's chance does not depend on what earlier moves drew.

```php
$random->int(1, 6);
$random->pick(['north', 'south']);
$random->shuffle($deck);
$random->randomizer();       // for a library that takes one, as tilttek/dice does
```

The seed is 64 hexadecimal digits drawn from the system's secure source when the table opens. Whoever holds the log can
replay the chance of every move, so a game with hidden information keeps the log away from the players, as it keeps
its state, until the game is over.

## The commitment to the seed

`GameLog::commitment()` is the SHA-256 digest of the seed. It tells nothing of the draws to come, so the players may
be shown it when the table opens; once the game is over, they are shown the log, seed included, and anyone checks that
every draw came from the seed committed to:

```php
$commitment = $log->commitment();                    // shown at the opening

$state = $engine->verify($game, $log, $commitment);  // once the game is over
```

`verify()` compares the digest of the log's seed with the commitment, in constant time, then replays the log. It
refuses with `InvalidGame` a seed that is not the one committed to — chance drawn again from another seed until a
result pleased someone — as well as a log that does not replay.

## Replaying

```php
$state = $engine->replay($game, $log);
```

The replay checks that the log is of this game and of this version of its rules, runs the setup, then applies every
move as it was played. It refuses with `InvalidGame` a move that no longer applies, and a move that draws at random
where it did not, or the reverse: the game is not pure, or its rules changed without a new version. A game whose rules
change raises its version; its old tables are then replayed by the version that wrote them, or closed.

The log keeps whether a move drew, not what it drew: the values come back from the seed. A log given another seed
replays too, to another state, which is what the commitment guards against.

## The state as plain values

`GameState::toArray()` writes the state as plain values — the game's data as the game wrote it —, and
`GameState::fromArray()` reads them back, checking the type of every field, the turn order, and that the seat acting and
the seats waiting are among the order of the turn. A shared cache keeps the state this way, rather than as a PHP object,
inside a `Snapshot` written by `Snapshot::toArray()` with the revision of the log and the commitment to the seed: what
it gives back is refused with `InvalidGame` when it is anything else, and the log is replayed instead.

## Taking a move back

```php
$table = $engine->undo($game, $table, 'seat:ann');
```

The last move is taken back when the game's definition allows it, the game is not over, the move drew nothing at
random — what it revealed cannot be hidden again —, and the seat taking it back made it; the table itself, a null seat,
takes back any such move. The table's state is the log without the move, replayed. `Engine::checkUndo()` applies the
same rules to a state and a last move, for a caller that holds no whole log.

Once the game is over, every undo throws `GameOver`, whoever asks. The log of a finished table, seed included, may be
shown to anyone to verify its draws, and the seed tells every draw to come: a move taken back then would let a player
play again knowing them. No move is made either: the engine refuses any move at a finished table with `IllegalMove`.

`Tables::finishedLog()` is the log to show: it throws `GameInProgress` while the game goes on, and `ConcurrentMove`
when the log it reads is not the one of the finished state — another seed or another number of moves, as when a table
is opened again under the same identifier between the two reads —, so that the seed of a table that goes on is never
shown.

## Bringing a state forward

```php
$header = $log->header();
$state = $engine->advance($game, $header, $engine->setup($game, $header), $log->moves);
$step = $engine->step($game, $header, $state, $move);   // $step->move to append, $step->state after it
```

`setup()` gives the state before the first move of a log known by its header. `advance()` applies the moves that follow
a state, each with the chance of its place, as they come from an iterable: a replay is the setup and every move, and a
cached state with the moves made since is brought up to date without the moves before it. It refuses a move that does
not follow the state, one past the header, or a state of another table. `step()` applies one more move to the state
after every move of the header, and gives the move to append with whether it drew.
