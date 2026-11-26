# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network, on the in-memory store and the
`MockClock` of `symfony/clock`: an edition from its draft to its announcement, with and without semifinal; nominees
proposed and written in, eligible or not, at most 100; ballots of each phase, replaced, refused outside their phase,
for a voter turned away, or for breaking their phase's rules; the first nominees written in one by one; shortlists and
their ties; finals won by a nominee, by "no award", shared by a tie, or without ballots; phases with fewer than two
options; sealed and published results; erased voters whose ballots still count; and the bounds of every value. In the
monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that runs awards

Build the service on the in-memory store, and advance the edition through its phases:

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\FanAwards;
use Tilttek\FanAwards\Store\InMemoryAwardStore;

$awards = new FanAwards(new InMemoryAwardStore(), new MockClock('2027-01-10 10:00:00', 'UTC'));
$awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel', method: 'borda')], semifinal: false);
$awards->advance('2027'); // nominations
```
