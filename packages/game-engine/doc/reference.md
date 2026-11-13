# Reference

| Type | Role |
|---|---|
| `Contract\Game` | A game's rules, behind one contract |
| `GameDefinition` | A game's phases, transitions, seats, options, out-of-turn moves, undo, version, preferences and statistics |
| `Phase`, `TurnOrder` | A phase, who acts in it and how many moves end a turn; the four turn orders |
| `Transition` | A change of phase, the phases it leaves from and the phase it leads to |
| `Phases` | The phases as a state machine of Symfony Workflow, and its Mermaid diagram |
| `Option`, `OptionKind` | An option a game offers, its kind, default and allowed values |
| `TableOptions` | The options of one table, checked by the OptionsResolver |
| `Preferences` | The preferences of one player, checked by the OptionsResolver, given to the view of their seat |
| `Statistic`, `StatisticScope` | A statistic a game reports at its end, for the table or for each seat |
| `Statistics` | The values of the statistics an outcome reports |
| `Engine` | Opens a table, plays a move, replays a log or brings a state forward, verifies it against the commitment to its seed, takes a move back, gives a seat's view |
| `Table` | A table's log and state after a step |
| `Step` | One move applied: the move to append and the state it leads to |
| `GameState` | The state a log leads to: phase, turn, initiative, data and outcome; written and read back as plain values |
| `Turn` | Whose turn it is: round, order, acting seat, waiting seats, moves in the turn |
| `Outcome` | How a game ended: the winners, the reason and the statistics |
| `Move` | A move: type, seat and payload; the engine's moves |
| `LoggedMove` | A move as the log keeps it, numbered, with whether it drew at random |
| `GameLog` | The truth of a table, written and read back as plain values, with the commitment to its seed |
| `LogHeader` | What a log holds besides its moves: seed, seats, options, number of moves, revision and lineage |
| `Snapshot` | A state as a cache keeps it, with the revision and the commitment it was built from |
| `Randomness` | The chance of one step, seeded by the table's seed and the step's place; the commitment to a seed |
| `Tables` | The tables of an application, stored, cached, guarded, read once per request and published |
| `Games` | The games an application offers, by name |
| `TableUpdate` | What a publisher tells the players: identifiers and counters only |
| `Contract\LogStore`, `Store\InMemoryLogStore` | Where the logs are kept |
| `Contract\StateCache`, `Store\InMemoryStateCache`, `Store\NullStateCache` | Where the states are cached |
| `Contract\TablePublisher`, `Store\NullTablePublisher` | How the players learn that a table changed |
| `Scene\RoleplayingScene` | The ready game for the phases of a role-playing scene |
| `Exception\GameEngineException` and its five exceptions | [Exceptions](exceptions.md) |
