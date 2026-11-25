# tilttek/escape-room-bundle

**Escape rooms for a Symfony application: the host's console, the hint screen in real time, bookable sessions, and a
public leaderboard by the team's consent.**

tilttek/escape-room-bundle integrates `tilttek/escape-room` with Symfony. The rooms — their scenario of puzzles, time,
penalties and team sizes — are written in the configuration, or given by the application's own catalog. Each game is
kept in Doctrine DBAL as the plain values the library writes, in UTC, with the moment its countdown reaches zero; the
results of the teams that escaped and what the hint screen shows are kept beside it, in five tables the bundle adds to
the schema the ORM generates. `GameDesk` plays each move by the game's identifier: it reads the game, moves it, writes
it back, records the result of an escape, and tells the screens.

The host drives a game from a console — push a hint, skip a puzzle, send a message, clear the screen, pause, resume, end
the game —, each form working without a script and sent in the background by a Stimulus controller. The hint screen in
the room shows the countdown, the hint or the message, and celebrates an escape with the `Celebration` of the PHIL UX
kit; it hears of each move through Mercure when a hub is there, or reads the game again at intervals. A schedule for
Symfony Scheduler ends the games whose countdown reached zero, once each. The sessions are booked on `phil/reservation`
when it is installed, under one lock per session in the database. The public leaderboard shows a team only once it
agrees, and it may change its mind; a member's games, results, screens and bookings are exported and pseudonymised with
their account through `phil/privacy-bundle`.

It is for a Symfony application that runs escape rooms, in a venue or online. The library alone suits an application
without Symfony.

It requires `tilttek/escape-room`, Doctrine DBAL, Twig and Symfony's container, HTTP kernel, routing and CSRF; the ORM,
the console, Symfony Scheduler, Mercure, the rate limiter, Twig components, `phil/reservation` and `phil/privacy-bundle`
are optional, and the bundle registers what uses them only when they are installed.

## Status

| Part | State |
|---|---|
| Configuration of the rooms, the stores, the access and the integrations | Implemented and tested |
| `GameDesk`: the moves of a game by its identifier, the results, the hint screen, the expiries, the leaderboard | Implemented and tested |
| `DbalGameStore`, `DbalResultStore`, `DbalScreenStore`, `DbalSeatLedger` and the five tables, with the schema listener | Implemented and tested |
| The memory stores for tests, reset between two requests of a worker | Implemented and tested |
| The reservations on `phil/reservation`, wired when it is installed | Implemented and tested |
| The schedule `tilttek_escape_room` on Symfony Scheduler and the command `tilttek:escape-room:expire` | Implemented and tested |
| The console and the hint screen, built of the kit's recipes, with their Stimulus controllers | Implemented and tested |
| The moves told through Mercure, the hub detected | Implemented and tested |
| The consent and the public leaderboard | Implemented and tested |
| Export and erasure of a member for `phil/privacy-bundle` | Implemented and tested |
| A Flex recipe | Prepared in `recipe/0.1` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes, the tables and the access. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Games](doc/games.md) | `GameDesk`: starting a game, its moves, the results and the leaderboard. |
| [Screens](doc/screens.md) | The console, the hint screen, the consent and the leaderboard pages; their controllers and events. |
| [Real time](doc/real-time.md) | Mercure, the topics, and the screens without a hub. |
| [Bookings](doc/bookings.md) | The reservations of the sessions on `phil/reservation`, or the application's. |
| [Scheduling](doc/scheduling.md) | The schedule and the command that end the games whose countdown reached zero. |
| [Database](doc/database.md) | The five tables and what each column holds. |
| [Accessibility](doc/accessibility.md) | What the screens do for every player. |
| [Privacy](doc/privacy.md) | What is exported and pseudonymised for a member. |
| [Extension points](doc/extension-points.md) | What an application replaces. |
| [Reference](doc/reference.md) | The classes, services, routes and exceptions. |
| [Testing](doc/testing.md) | The bundle's tests, and testing an application. |

## Quick start

```yaml
# config/packages/tilttek_escape_room.yaml
tilttek_escape_room:
    access: App\EscapeRoom\Access
    rooms:
        cell:
            title: The cell
            duration: 1800
            scenario: { id: cell, finale: door, puzzles: [{ id: door, title: The door, answers: [key], hints: [{ text: Under the bed }] }] }
    schedule: { enabled: true }
```

```php
$desk->start('g-01J8', 'cell', ResourceReference::of('team', 'owls'));
// The host opens /escape-room/console/g-01J8; the room shows /escape-room/screen/g-01J8.
```

## Requirements

PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.6.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
