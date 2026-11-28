# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Contract\TavernGame` | A turn-based game of the engine that keeps its own turns and accepts the timeout of a turn |
| `Turns` | Whose turn it is, kept in the game's data |
| `TavernTables` | Opens and plays the tables, tells the turn timer, plays timeouts, shows views and endings |
| `Contract\TurnTimer` | The optional clock of the turns |
| `Store\NullTurnTimer`, `Store\InMemoryTurnTimer` | No clock, and the running turns in memory for tests |
| `Ending`, `Tone` | How a game ended for a seat: a victory, a shared victory or a good game |
| `TavernGames` | The tavern games of an application, by name, and the same games for the engine |
| `DiceCup` | Six-sided dice rolled by `tilttek/dice` with the chance of a move |
| `Contract\Simulation` | A position played to its end at random |
| `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial` | Odds over simulated endings, wins, ties and losses apart |
| `Odds\CargoChase` | The turn of a seat at Ship, Captain and Crew, chased to its end |
| `Game\Pig` | The dice game of push-your-luck |
| `Game\ShipCaptainCrew` | The dice game of the ship, the captain, the crew and the cargo |
| `Game\CrazyEights` | The card game of shedding, eights wild |
| `Exception\TavernGamesException`, `InvalidTavernGame` | [Exceptions](exceptions.md) |
