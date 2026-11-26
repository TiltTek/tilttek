# tilttek/fan-awards

**Fan awards voted in rounds: open nominations, a semifinal open to write-ins, a ranked final with "no award", counted by `phil/poll`.**

tilttek/fan-awards runs the awards a community gives each year — best novel, best game, best stream — from the first
nomination to the ceremony. An edition holds its categories, each with its own rules: how many nominees a nomination
ballot holds, how many go on to the semifinal and to the final, and the counting method of the final. Organisers may
put nominees forward; voters nominate freely, writing in a work the list lacks, which the application's eligibility
rules check — published in the award year, of the category's kind. The most nominated go on to a semifinal, where a
voter still may add a nominee, then to a ranked final that offers "no award" beside the finalists, so that a list pushed
by a group can be refused.

Every count is made by `phil/poll`, nothing is counted again here: the nominations and the semifinal by the count of
choices, the final by the category's ranked method — the instant runoff, the Borda count or the Schulze method. Once
the final closes the organisers read the results; once the winners are announced, everyone reads them with the
detailed count of every phase. Who may vote is the application's rule, never a payment nor a purchase. Voters are
references the application gives; erasing one keeps their ballots, anonymous, in the counts.

It is for a community site, a convention or a fan club. It is a plain PHP library whose dependencies are `phil/poll`,
`phil/resource` and `psr/clock`.

## Status

| Part | State |
|---|---|
| `Edition`, `Category`, `Nominee`, `Phase`: editions, their categories and rules, nominees, phases | Implemented and tested |
| `FanAwards`: creating, proposing, voting with write-ins, advancing and counting, previewing, publishing, erasing | Implemented and tested |
| `PhaseCount`, `Results`: the count of each phase, the winners, "no award" | Implemented and tested |
| `Contract\NomineeEligibility`, `Contract\VoterEligibility`, with `Eligibility\AnyNominee` and `Eligibility\AnyVoter` | Implemented and tested |
| `Contract\AwardStore`, with `Store\InMemoryAwardStore` | Implemented and tested |
| A Symfony bundle: Doctrine store, ballot forms, scheduled phases, translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Editions and phases](doc/editions.md) | Categories, nominees, the phases, proposing, voting, write-ins, eligibility. |
| [Counting and results](doc/counting.md) | Shortlists and ties, the final, "no award", sealed and published results, erasure. |
| [Extension points](doc/extension-points.md) | The store, the eligibility rules, the counting methods and the clock. |
| [Exceptions](doc/exceptions.md) | `FanAwardsException` and its exceptions, with the refusals and their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that runs awards. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/fan-awards
```

```php
use Tilttek\FanAwards\Category;
use Tilttek\FanAwards\FanAwards;
use Tilttek\FanAwards\Nominee;
use Tilttek\FanAwards\Store\InMemoryAwardStore;

$awards = new FanAwards(new InMemoryAwardStore(), $clock);

$awards->create('2027', 'Fan Awards 2027', [new Category('novel', 'Best novel')]);
$awards->propose('2027', 'novel', new Nominee('the-long-road', 'The Long Road'));

$awards->advance('2027');                     // nominations open
$awards->vote('2027', 'novel', $voter, ['the-long-road', new Nominee('night-ferry', 'Night Ferry')]);

$awards->advance('2027');                     // the semifinal, on the nominations' shortlist
$awards->advance('2027');                     // the final, "no award" among its options
$awards->vote('2027', 'novel', $voter, ['night-ferry', 'no-award']);

$awards->advance('2027');                     // counted: preview() for the organisers
$awards->advance('2027');                     // announced
$awards->results('2027', 'novel')->winners;   // and every phase's count
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
