# tilttek/puzzle-builder

**Puzzles for a scenario, checked on the server: normalized answers, partial answers, bounded guesses, tiered hints and
a validated unlock graph.**

A scenario holds its puzzles: a riddle, a cipher, a code to find. Each puzzle accepts one or more answers, read the way
the players type them — `Le Chêne!`, `le chene` and `LECHENE` are one answer —, and may tell the players that a guess
is a step on the way: "Which twelve?". The guesses of a team on a puzzle are counted in a period, so that a script
cannot try every word. Hints come in tiers, the first a nudge, the last close to the answer, asked by the players or
pushed by the host. A puzzle opens once those it requires are solved, once enough puzzles are solved, or when the host
hands it out; the graph is checked when the scenario is made: no cycle, and no puzzle a game cannot reach.

The answers stay on the server. A guess is checked there, compared in constant time, and nothing sent to the browser —
the view of a game, the events — holds an answer or anything that checks one.

It is a plain PHP library and keeps nothing: each move takes the team's progress and gives the progress to store after
it, and its events let an application, or a bundle, push the moves to the players' screens. It is the puzzle engine of
escape rooms and murder mysteries, which add their own rules on top.

## Status

| Part | State |
|---|---|
| `Scenario`, `Puzzle`, `Unlock`, `Hint`: the puzzles and their graph, checked; read from plain values or JSON | Implemented and tested |
| `Answer\AnswerKey`, `Answer\AnswerNormalizer`, `Answer\Verdict`: accepted and partial answers, normalized | Implemented and tested |
| `PuzzleEngine`: guesses, hints, puzzles opened or skipped by the host, the status of each puzzle and the view | Implemented and tested |
| `Progress`, `Record\Solved`, `Record\HintTaken`: the state of a game, written and read back as plain values | Implemented and tested |
| `Contract\GuessLimiter`, with `Limiter\RateLimiterGuessLimiter` and `Limiter\InMemoryGuessLimiter` | Implemented and tested |
| `View\ScenarioView`, `View\PuzzleView`: what the players see, without answers | Implemented and tested |
| Events dispatched through PSR-14, naming identifiers only | Implemented and tested |
| Exceptions under one `PuzzleBuilderException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Usage](doc/usage.md) | Scenarios, answers, guesses, hints, unlocks, the host's moves, storing a game and the view. |
| [Security](doc/security.md) | Why the answers stay on the server, the limit of guesses, and the bounds of every input. |
| [Drives and safeguards](doc/drives-and-safeguards.md) | Where the library stands in Tilttek's grid of drives, and the rules it keeps. |
| [Extension points](doc/extension-points.md) | Checks of answers, limiters, events, and the games built on the engine. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role, and its exceptions. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\Scenario;

$scenario = Scenario::fromJson('{"id": "manor", "finale": "vault", "puzzles": [
    {"id": "clock", "title": "The clock", "answers": ["Midnight"], "partials": {"twelve": "Which twelve?"},
     "hints": [{"text": "Look at the hands"}, {"text": "Both point up"}]},
    {"id": "painting", "title": "The painting", "answers": ["Le Chêne"]},
    {"id": "vault", "title": "The vault", "answers": ["4521"], "unlock": {"requires": ["clock", "painting"]}}
]}');
$engine = new PuzzleEngine(new InMemoryGuessLimiter(10, 60), $eventDispatcher);
$team = ResourceReference::of('team', '7');

$result = $engine->guess($scenario, Progress::start(), $team, 'clock', 'twelve');
$result->outcome;   // GuessOutcome::Partial
$result->message;   // "Which twelve?"
$result = $engine->guess($scenario, $result->progress, $team, 'clock', 'MIDNIGHT');
$result->solved();  // true
$engine->view($scenario, $result->progress)->toArray();   // for the browser: statuses, hints, no answer
$result->progress->toArray();                             // to store until the next move
```

## Requirements

PHP 8.5 or later with `intl` and `mbstring`, `phil/resource` 0.1, `psr/clock`, `psr/event-dispatcher` and
`symfony/clock`; `symfony/rate-limiter` to count the guesses with its limiters.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
