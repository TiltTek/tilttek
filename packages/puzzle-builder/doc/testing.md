# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: answers normalized step by step and option by
option; accepted and partial answers, and keys refused at each bound; puzzles, unlocks and hints refused at each bound;
scenarios with cycles, puzzles out of reach, a missing requirement or finale, and every malformed field of their JSON;
guesses right, partial, wrong, malformed and throttled; hints tier after tier; puzzles opened and skipped by the host;
the view, checked to hold no answer; progress written and read back, and refused field by field; both limiters. In the
monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/scenario.php` is the fuzz target of a scenario: any text gives a scenario whose order holds every puzzle
once, each after the puzzles it requires, or is refused with `InvalidScenario`, never another exception.
`composer fuzz` runs it.

`tests/Benchmark/PuzzleEngineBench.php` measures, on a chain of 200 puzzles, reading and checking the scenario from
JSON, a guess on the last puzzle once 199 are solved, and the view of that game.

## Testing an application

Give `PuzzleEngine` a memory limiter and a clock that does not move:

```php
$clock = new MockClock('2026-11-13 20:00 UTC');
$engine = new PuzzleEngine(new InMemoryGuessLimiter(10, 60, $clock), $dispatcher, $clock);
```

`$clock->sleep(60)` ends a window of the limiter. A test of a check of its own runs its guesses through
`PuzzleEngine::guess()` and asserts on `GuessResult::$outcome`, then that `PuzzleEngine::view()` holds none of its
answers.
