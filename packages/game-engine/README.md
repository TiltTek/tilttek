# tilttek/game-engine

**A state machine for online table games and role-playing scenes, without a single game rule: each table is a log of
moves with its seed, and its state a cache rebuilt by replaying the log.**

tilttek/game-engine runs the tables of turn-based games online — a board or card game, a tavern game, a mini-game —
and the turns and phases of a role-playing scene: exploring, then an encounter in initiative order, then its
aftermath. It holds no rules: a game implements one contract and declares its phases, the transitions between them,
how many seats a table holds, the options it offers, who acts in each phase — anyone, one seat after the other, by
initiative, or every seat once a round — and how many moves end a turn. The engine checks the turns, changes the phase
through a state machine of Symfony Workflow, orders seats by initiative and lets a seat delay its turn, checks the
table options with Symfony's OptionsResolver, and asks the game whether a move is allowed and what it changes.

The truth of a table is its log: the game and the version of its rules, a seed of 256 bits, the seats and options, and
every move in order, with whether it drew at random. The state is a cache: replaying the log — the setup, then each move
with the chance of its place in the log — gives the same state, which tests check and which a replay verifies, refusing
a log whose moves no longer apply or draw differently; the players may be shown a commitment to the seed when the table
opens, against which anyone verifies the replay once the game is over. The last move may be taken back when the game
allows it, the game is not over and the move revealed nothing. Appending a move checks the number of moves and the
revision of the log the player saw, so that two changes made at once never both land; a read costs the header of the log
and the moves made since the cached state, read window by window, once per request; and every change is told to a
publisher port — a Mercure hub, through the bundle — with identifiers only; each player then reads the view of their
seat, which leaves out the others' secrets and follows their own preferences. A game that ends reports the statistics it
declares, for the table and for each seat.

It is for an application that hosts games or scenes played at a distance and wants every table auditable, able to be
replayed and safe against a player who acts out of turn. It is a plain PHP library whose dependencies are
`symfony/workflow`, the machine of the phases, and `symfony/options-resolver`, the options of a table. A ready game,
`RoleplayingScene`, runs the phases of a scene with no rules of any system.

## Status

| Part | State |
|---|---|
| `Contract\Game`, `GameDefinition`, `Phase`, `Transition`, `TurnOrder`: a game's rules behind one contract, its phases and turns declared | Implemented and tested |
| `Phases`: the phases as a state machine of Symfony Workflow, checked by its validator and drawn by its Mermaid dumper | Implemented and tested |
| `Option`, `OptionKind`, `TableOptions`: the options of a table, checked by the OptionsResolver | Implemented and tested |
| `Preferences`: the preferences of each player, given to the view of their seat, outside the log | Implemented and tested |
| `Statistic`, `StatisticScope`, `Statistics`: the statistics of the end of a game, declared and checked | Implemented and tested |
| `Engine`, `Table`, `GameState`, `Turn`, `Outcome`: tables opened, moves applied, turns, initiative, delays, phases, endings | Implemented and tested |
| `GameLog`, `LoggedMove`, `Move`, `Randomness`: the log as the truth, written and read back, deterministic chance, replay verified against the commitment to the seed, and undo | Implemented and tested |
| `GameState::toArray()`, `GameState::fromArray()`: the state as plain values, for a shared cache | Implemented and tested |
| `Tables`, `Games`, `Contract\LogStore`, `Contract\StateCache`, `Contract\TablePublisher`, `TableUpdate`: tables kept, cached, guarded against concurrent moves and published | Implemented and tested |
| `LogHeader`, `Snapshot`, `Engine::advance()`: the log read without its moves and by windows, the cached state tied to the revision of its log, each table read once per request | Implemented and tested |
| `Store\InMemoryLogStore`, `Store\InMemoryStateCache`, `Store\NullStateCache`, `Store\NullTablePublisher` | Implemented and tested |
| `Scene\RoleplayingScene`: exploration, encounter by initiative and aftermath, led by the first seat | Implemented and tested |
| Exceptions under one `GameEngineException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Writing a game](doc/writing-a-game.md) | The `Game` contract, the definition, phases, turn orders, options, preferences, views, endings and statistics. |
| [Phases and the state machine](doc/phases.md) | Transitions on Symfony Workflow, why it fits replay, and the diagram of a game. |
| [Tables, moves and turns](doc/tables-and-moves.md) | Opening a table, the engine's moves, turns, initiative and delays, out-of-turn moves. |
| [The log and replay](doc/log-and-replay.md) | The log as the truth, the seed and chance, the commitment to the seed, replaying, verifying, undoing, and the state as plain values. |
| [Storing and publishing](doc/storing-and-publishing.md) | `Tables`, the log store, the state cache, concurrent moves and the publisher port. |
| [The role-playing scene](doc/roleplaying-scene.md) | The ready game for the phases of a scene. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `GameEngineException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks, and testing a game. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\GameEngine\{Games, Move, Tables};
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Store\InMemoryLogStore;

$tables = new Tables(new Games(new RoleplayingScene()), new InMemoryLogStore());

$tables->open('roleplaying-scene', 'scene:12', ['seat:gm', 'seat:ann', 'seat:bob']);
$tables->play('scene:12', Move::initiative(['seat:ann' => 14, 'seat:bob' => 9], 'seat:gm'));
$state = $tables->play('scene:12', Move::transition('start-encounter', 'seat:gm'), expected: 1);

$state->turn->toAct();                       // ['seat:ann']
$tables->view('scene:12', 'seat:bob');       // what Bob sees
$tables->commitment('scene:12');             // shown to the players from the opening
$tables->finishedLog('scene:12');            // seed included, once the game is over, to verify every draw
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
