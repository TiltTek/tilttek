# Commands

| Command | What it does |
|---|---|
| `tilttek:game-engine:setup [--dump-sql]` | Creates the tables of the logs on their connection when they are missing, or shows their SQL |
| `tilttek:game-engine:verify <table> [--commitment=…]` | Replays the log of a table, against the commitment to its seed when one is given |

`setup` is registered with the `doctrine` store; it compares the connection's schema with the tables of the logs and
runs only what is missing, so that running it twice changes nothing.

`verify` loads the log of a table, replays it with its game, and shows the game and its version, the number of moves,
the phase, the outcome and the commitment to the seed. Given the commitment the players were shown, it first checks
that the seed of the log is the one committed to. A log that no longer replays, or whose seed is not the one committed
to, fails the command.
