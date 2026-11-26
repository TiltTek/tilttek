# tilttek/predictions

**Predictions on dated outcomes of events, settled in points: nothing staked, and a wrong pick costs nothing.**

tilttek/predictions lets an application ask its members a question about a dated outcome — who wins the final, whether
the campaign ends by its deadline —, take their picks until the deadline, and pay the right ones in points of
`tilttek/points` once it declares the outcome. A question is scored by choice — the member picks one outcome, and a
right pick earns the question's points —, or by probability: the member gives each outcome a probability, and earns a
share of the points that grows as the Brier score of their forecast falls, which rewards calibrated confidence rather
than a bold guess.

Nothing is ever staked, not even in play money: a member never gives points to predict, and the library holds no
market and no odds. A question is about an outcome of an event the application declares, never about a person: its
outcomes are the application's keys of the event's results, which cannot be a member's reference.

The settlement is paid once, whatever happens: each prediction's points are written under a key built from its random
identifier, which the ledger writes once, so that a settlement run again, or by two processes at once, pays nothing
twice; a cancelled question pays nothing. Erasing a member deletes their predictions.

It is for a community that wants friendly forecasts on its finals, contests and campaigns without turning them into
betting. It is a plain PHP library whose dependencies are `tilttek/points`, `phil/resource` and `psr/clock`.

## Status

| Part | State |
|---|---|
| `Predictions`: ask, predict until the deadline, resolve, settle, cancel, read, erase | Implemented and tested |
| `Question`, `Pick`, `Prediction`, `Brier`, `Settlement` | Implemented and tested |
| `Contract\PredictionStore`, with `Store\InMemoryPredictionStore` | Implemented and tested |
| A store on Doctrine DBAL, and a Symfony bundle | Not started |
| Tournament brackets filled in ahead | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Asking and settling](doc/predictions.md) | Questions, picks, the deadline, scoring, the settlement paid once, cancelling, erasing. |
| [Scoring](doc/scoring.md) | Choice and probability, the Brier score in whole numbers, worked values. |
| [Extension points](doc/extension-points.md) | The store, the currency, the source and the batch size. |
| [Exceptions](doc/exceptions.md) | `PredictionsException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that asks questions. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/predictions
```

```php
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Predictions;
use Tilttek\Predictions\Store\InMemoryPredictionStore;

$predictions = new Predictions(new InMemoryPredictionStore(), $points, $clock);

$predictions->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00 UTC'), 50);
$predictions->predict('final:2026', $member, Pick::choice('home'));
$predictions->resolve('final:2026', 'home');    // 50 points to each right pick, once
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
