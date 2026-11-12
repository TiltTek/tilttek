# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers the calendars and their refusals, the boundaries of each day in the calendar's timezone — the first
and last second, the days of 25 and 23 hours when the clocks change, a midnight a change of time skips —, the three
opening policies and the closing, the surprises, idempotent openings and a request that loses a race, the boxes as a
member sees them, erasure, and the store in memory through `OpeningStoreTestCase`, the contract any store passes.
`tests/Benchmark/BoxesBench.php` measures the boxes a page reads, on calendars of 24 and 92 boxes. In the monorepo,
Infection holds the library at 100 % of covered mutants.

## Testing code that opens boxes

Build `AdventCalendar` with the store in memory, a clock you move — `Symfony\Component\Clock\MockClock` — and a
dispatcher that writes down its events:

```php
$clock = new MockClock('2026-12-03 23:59:59', 'Europe/Paris');
$advent = new AdventCalendar(new InMemoryOpeningStore(), $clock, $dispatcher);

self::assertTrue($advent->open($calendar, $member, 3)->first);

$clock->modify('2026-12-04 00:00:00');
self::assertSame(BoxState::Missed, $advent->box($sameDayCalendar, $member, 3)->state);
```

Set the clock in the calendar's timezone, or give the moment's offset, to test a day's boundaries.
