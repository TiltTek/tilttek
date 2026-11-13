# Writing a game

A game implements `Contract\Game`. It holds every rule; the engine holds none. Every method is pure: it reads the
state, the move and the randomness it is given, and nothing else — no clock, no storage, no service with a state —,
so that replaying a log gives the same state ([The log and replay](log-and-replay.md)).

| Method | What it gives |
|---|---|
| `definition()` | The `GameDefinition`, the same at every call |
| `setup()` | The game's data when a table opens |
| `allows()` | Whether a move is allowed now, the engine's moves included |
| `play()` | The game's data after one of its own moves, or `IllegalMove` |
| `enter()` | The game's data when the table enters a phase by a transition |
| `outcome()` | How the game ended, with its statistics, or null while it goes on |
| `view()` | What one seat sees of the state, shaped by its player's preferences, or the audience when the seat is null |

The game's data is a mapping of plain values the game alone reads and writes, in `GameState::$data`.

## The definition

```php
new GameDefinition(
    'race',
    phases: [new Phase('race', TurnOrder::Sequential, movesPerTurn: 1)],
    transitions: [],
    initial: null,                 // the first phase
    minimumSeats: 2,
    maximumSeats: 4,
    options: [Option::integer('target', 20, 10, 100), Option::choice('track', 'short', ['short', 'long'])],
    outOfTurn: ['race.taunt'],
    undo: true,
    version: '1',
    preferences: [Option::boolean('leader-first', false)],
    statistics: [Statistic::table('rounds'), Statistic::seat('position')],
);
```

| Argument | What it declares |
|---|---|
| `game` | The game's name, lowercase words joined by `-` or `_` |
| `phases` | Its phases, at least one, each with its turn order and the moves that end a turn |
| `transitions` | The changes of phase it allows ([Phases and the state machine](phases.md)) |
| `initial` | The phase a table starts in; the first when null |
| `minimumSeats`, `maximumSeats` | How many seats a table holds, 1 to 64 |
| `options` | The options it offers its tables |
| `outOfTurn` | The move types a seat may make out of its turn |
| `undo` | Whether the last move may be taken back, when it drew nothing at random |
| `version` | The version of its rules: a log written under another is not replayed |
| `preferences` | The preferences it offers each player, which change the view of a seat only |
| `statistics` | The statistics its outcome reports, for the table or for each seat |

A definition that does not hold together is refused with `InvalidGame`: two phases, options, preferences or
statistics of one name, a
transition to a phase the game lacks, two transitions of one name leaving one phase, an engine move declared out of
turn.

## Turn orders

| `TurnOrder` | Who acts |
|---|---|
| `Free` | Anyone, at any time: there are no turns |
| `Sequential` | One seat after the other, in the order of the table, round after round |
| `Initiative` | One seat after the other, the highest initiative first, round after round |
| `Simultaneous` | Every seat once a round, in any order; the round ends when all have ended their turn |

A phase with turns may end a turn by itself after 1 to 100 moves of the game, `movesPerTurn`; otherwise a seat ends
it with `engine.end-turn` ([Tables, moves and turns](tables-and-moves.md)).

## Options

An `Option` is a boolean, a whole number between bounds, or a choice among texts, with its default. A table's
`TableOptions` are resolved by Symfony's OptionsResolver when it opens and when its log is replayed: an option the
game does not offer, or a value it does not allow, is refused. A game reads them with `bool()`, `int()` and `string()`
on `$state->options`.

## Preferences

A preference is an `Option` the game offers each player rather than each table: how the cards of a hand are sorted,
whether the positions are listed from the leader down, a colour scheme for the colour-blind. A seat's `Preferences`
are resolved by the same OptionsResolver, from the values its player chose, and given to `view()`; a game reads them
with `bool()`, `int()` and `string()`. They are not part of the log and never reach `play()`: two players with other
preferences share the same state, and a replay ignores them. A preference changes how a seat sees what it may see,
never what it may see.

```php
public function view(GameState $state, ?string $seat, Preferences $preferences): array
{
    $data = $this->withoutTheOthersSecrets($state, $seat);

    if ($preferences->bool('leader-first')) {
        arsort($data['positions']);
    }

    return $data;
}
```

## Allowing and playing moves

The engine checks the seat and the turn first, then asks `allows()`. A game refuses here what its rules forbid at this
moment — the engine's moves included: a game where only the first seat changes the phase says so for
`engine.transition`. A move type the game does not know is refused by `allows()` too.

`play()` receives only the game's own moves. It reads the payload with `$move->int()` and `$move->text()`, draws
chance from the `Randomness` it is given, never from elsewhere, and returns the new data, or throws `IllegalMove` for
a payload its rules refuse.

## Views and secrets

`view()` filters the data for one seat: a hand of cards, a hidden role, a secret objective. The engine never sends the
data itself to a player; the publisher tells the players that the table changed, and each reads the view of its seat.

## Endings

`outcome()` is read after every move. An `Outcome` lists the winning seats — none for a draw or a scene that simply
ends — and the game's word for the ending; once a table has one, every move is refused.

## Statistics of the end

A game declares the statistics it reports when it ends — `Statistic::table('rounds')`, `Statistic::seat('points')` —
and its outcome gives their values, whole numbers, in a `Statistics`: those of the table by name, those of each seat by
seat and name. The engine checks them after every move: a statistic the game does not declare, or one of the wrong
scope, or the statistics of a seat not at the table, is refused with `InvalidGame`, a fault of the game rather than of
the player. A game reports only what it counts; a statistic it leaves out is read as null.

```php
return Outcome::won(['ann'], 'target-reached', new Statistics(
    ['rounds' => $state->turn->round],
    ['ann' => ['position' => 21], 'bob' => ['position' => 17]],
));
```

The statistics are part of the state, rebuilt with it from the log; `$state->outcome->statistics->ofSeat('ann',
'position')` reads one.
