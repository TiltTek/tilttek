# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: rooms and chains, refused at each bound; a
game played to its escape with hints asked and pushed, a skip, and the final time; pauses, a pause at the end, the time
up refused then expired, pauses counted in the end; the hint screen; consent and results; every move of a game over
refused; games written and read back, and refused field by field; sessions booked private and shared, every reason of
refusal, the reservations refusing last; the same scenarios of `Contract\SessionReservations` run against the memory
store and against `Reservation\BookableSessionReservations`, whose rules of `phil/reservation` are told as refusals; the leaderboard by consent, ties and limits. In the monorepo, Infection holds
the library at 100 % of covered mutants.

`tests/Fuzz/game.php` is the fuzz target of a stored game: any JSON gives a game of the room that writes back as it was
read, or is refused with `InvalidRoom`, never another exception. `composer fuzz` runs it.

`tests/Benchmark/EscapeRoomsBench.php` measures a guess and the timer of a game of 50 chained puzzles with 49 solved and
98 hints revealed, a stored game read back, and the leaderboard of 10 000 results.

## Testing an application

Give the services a clock that does not move, and memory stores:

```php
$clock = new MockClock('2026-11-13 20:00 UTC');
$rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(10, 60, $clock), $dispatcher, $clock), $dispatcher, $clock);
$booker = new SessionBooker(new InMemorySessionReservations(), $dispatcher, $clock);
```

`$clock->sleep(3_600)` runs the countdown to its end.
