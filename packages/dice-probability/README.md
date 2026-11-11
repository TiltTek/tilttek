# tilttek/dice-probability

**The exact probabilities of a dice expression — every total as a fraction, and the chance of a double.**

tilttek/dice-probability computes what an expression of `tilttek/dice` can give and how likely each value is: `4d6kh3`,
`2d20kh1`, `6d10>=7f1`, `(1d4)d6`, `1d6!`. Each probability is an exact fraction over the ways to roll — `6/36` for a 7
on `2d6` —, with the probability of at least and at most each value, the mean and the standard deviation. It also gives
the probability of events on the faces of a set of dice — at least one 1, exactly two sixes, a double, a straight, all
different, and their combinations — and compares two expressions, an attack against a defence. Explosions are followed
to a depth, the probability of going further stated; past a size, or for the few constructs it does not follow, a
seeded estimate takes over, marked as estimated, with its margin.

It is for any PHP code that explains dice to people: a probability page, a game designer weighing two mechanics, a
character sheet that shows the odds of a roll. `tilttek/dice-probability-bundle` integrates it with Symfony, with a
table, a chart and a Live Component.

It is a plain PHP library that depends only on `tilttek/dice`. Its large numbers are its own, without `bcmath` or
`gmp`. Sums of dice are convolutions, a plain die summed by a sliding window; keep and drop run over the sorted
multisets of the dice, never over every roll.

## Status

| Part | State |
|---|---|
| Exact distributions: arithmetic, functions, dice, rerolls, explosions, floors, transformations, filters, keep and drop, successes and failures, judgements of the whole set, counts and sides rolled first | Implemented and tested |
| At least, at most, mean, variance, standard deviation, a validator's probability | Implemented and tested |
| Events: counts of a face or a validator, dice of a kind, straights, all equal, all different, all matching, composed | Implemented and tested |
| Two expressions compared | Implemented and tested |
| Explosions to a depth with what remains; seeded estimates with their margin | Implemented and tested |
| Exact numbers of any size | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and a first distribution. |
| [Distributions](doc/distributions.md) | `Probabilities::distribution()`, `Distribution`, and what is computed exactly. |
| [Events](doc/events.md) | `chance()`, the events, the reference example and `Faces`. |
| [Comparing two expressions](doc/comparing.md) | `compare()` and `Versus`. |
| [Explosions and estimates](doc/explosions-and-estimates.md) | `Settings`, the depth of explosions, the estimates and their margin. |
| [Exact numbers](doc/exact-numbers.md) | `Natural` and `Fraction`. |
| [Exceptions](doc/exceptions.md) | `ProbabilityException`, `CannotCompute` and the invalid arguments. |
| [Extension points](doc/extension-points.md) | Events and dice of the application. |
| [Testing](doc/testing.md) | The library's tests, and how to test code that uses it. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require tilttek/dice-probability
```

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Probabilities;

$dice = new DiceRoller();
$probabilities = new Probabilities();

$distribution = $probabilities->distribution($dice->parse('2d6'));
(string) $distribution->probability(7);                // "6/36"
(string) $distribution->atLeast(10)->reduced();         // "1/6"

$oneOrDouble = new AnyOf(Count::atLeastOne(1), OfAKind::double());
(string) $probabilities->chance($oneOrDouble, $dice->parse('3d6'))->probability;   // "156/216"

(string) $probabilities->compare($dice->parse('1d20+5'), $dice->parse('2d10+4'))->first->reduced(); // "1/2"
```

## Requirements

PHP 8.5 or later, and `tilttek/dice`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
