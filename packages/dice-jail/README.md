# tilttek/dice-jail

**The jail for unlucky dice, and the statistics behind it: an honest goodness-of-fit test, explained plainly.**

A die that keeps rolling ones goes to jail: a player sends it there for an evening or until they release it, and the
jail keeps the history of each die. It is a gesture of play — jailing needs no proof and changes nothing in how the die
rolls.

The statistics are what a player shows the table. `GoodnessOfFit` runs Pearson's chi-squared test on the rolls of one
die against the exact probabilities of a fair one — a uniform die, or any distribution `tilttek/dice-probability`
computes —, refuses to judge before every face is expected five times, and gives a p-value and a verdict:
« not enough rolls », « nothing unusual », « somewhat unusual » or « very unusual ». `Explanation` says it in plain
English or French, with the average roll beside the expected one. No sentence ever says a die is loaded: the most
unusual verdict asks for a look at the die, or for new rolls, and the rolls of digital dice are said to come from a
generator that serves every die alike.

It is for a table that plays online or writes down its physical rolls: a game master's screen, a dice tower, a
character sheet. It is a plain PHP library on `tilttek/dice` and `tilttek/dice-probability`; the application keeps the
history and the rolls through two ports.

## Status

| Part | State |
|---|---|
| `Jail\Warden`: jailing for a sentence or until release, releasing, the dice in jail, the history | Implemented and tested |
| `Jail\Inmate`, `Jail\JailEvent`, `Jail\EventKind`, `Jail\Evidence`: the sentence, the history and the statistics shown | Implemented and tested |
| `Statistics\GoodnessOfFit`, `Statistics\FitResult`, `Statistics\FaceCount`, `Statistics\Verdict`: the chi-squared test | Implemented and tested |
| `Statistics\Tally`, `Statistics\ExpectedFaces`: the rolls counted, the fair die from a range or a distribution | Implemented and tested |
| `Statistics\Explanation`, `Statistics\Language`, `Statistics\Origin`: the verdict in plain English and French | Implemented and tested |
| `Statistics\DieAssessor`, `Contract\RollSource`, `Store\InMemoryRollSource`: the test from recorded rolls | Implemented and tested |
| `Contract\JailStore`, `Store\InMemoryJailStore` | Implemented and tested |
| A roll source reading the roll journal of `tilttek/dice-bundle` | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [The jail](doc/jail.md) | Jailing, sentences, releases, the history, and what the jail is not. |
| [The statistics](doc/statistics.md) | The chi-squared test, the minimum of rolls, the verdicts and how to read them honestly. |
| [Explanations](doc/explanations.md) | The sentences in English and French, and what they never say. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `DiceJailException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\DiceJail\Jail\Evidence;
use Tilttek\DiceJail\Jail\Warden;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Language;
use Tilttek\DiceJail\Statistics\Tally;
use Tilttek\DiceJail\Store\InMemoryJailStore;

$result = new GoodnessOfFit()->test(Tally::of($rollsOfTheBlueD20), ExpectedFaces::uniform(20));
$result->verdict;                                       // Verdict::NothingUnusual, most of the time
new Explanation()->explain($result, Language::French);  // « Rien d’inhabituel sur 120 lancers : … »

$warden = new Warden(new InMemoryJailStore(), new NativeClock());
$warden->jail('member-7', 'd20:blue', 'Three natural ones in a row.', evidence: Evidence::of($result));
$warden->release('member-7', 'd20:blue', 'It has served its time.');
```

## Requirements

PHP 8.5 or later, with `mbstring`; `tilttek/dice` 0.3 and `tilttek/dice-probability` 0.1; a `psr/clock` implementation.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
