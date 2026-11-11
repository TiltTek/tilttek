# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers every value and its refusals, the journal in memory through `ActivityLogTestCase` — the contract any
journal passes —, the `Journal` and its observers, and the projector: batches, checkpoints, failures, rebuilds and live
projections. In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that records activity

Build the `Journal` over an `InMemoryActivityLog` with a clock you control, record through the code under test, then
read the journal back:

```php
$log = new InMemoryActivityLog();
$journal = new Journal($log, new MockClock('2026-11-07 12:00:00'));

$listener = new CommentPostedListener($journal);
$listener($event);
$listener($event); // a listener run twice

self::assertSame(1, $journal->totals(Filter::all()->verbs('news.commented'))->count);
```

A projection is tested the same way: append entries, run `Projector::catchUp()` with `InMemoryCheckpoints`, and look at
its state; `rebuild()` checks that it resets what it should.
