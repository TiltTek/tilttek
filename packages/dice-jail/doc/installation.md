# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `tilttek/dice` 0.3, whose rolled dice a tally reads, and `tilttek/dice-probability` 0.1, whose exact distributions
  give the faces of a fair die.
- An implementation of `psr/clock`, such as `symfony/clock`.

## Installing

```sh
composer require tilttek/dice-jail
```

## Wiring the library

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\DiceJail\Jail\Warden;
use Tilttek\DiceJail\Statistics\DieAssessor;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;

$warden = new Warden($jailStore, new NativeClock());     // Contract\JailStore: the history of each die
$assessor = new DieAssessor($rollSource);                // Contract\RollSource: the rolls of each die
$test = new GoodnessOfFit();
$explanation = new Explanation();
```

None keeps state between two calls, so one instance serves every request of a worker. `Store\InMemoryJailStore` and
`Store\InMemoryRollSource` keep everything in the process, for tests and scripts; their `reset()` empties them.
