# Installation

## Requirements

- PHP 8.5 or later.
- `tilttek/dice` 0.1, which reads the dice notation and rolls an expression from a seed.

## Installing

```sh
composer require tilttek/secret-rolls
```

## Wiring the library

`SecretRolls` takes a store, and optionally a publisher, a `DiceRoller` and the randomizer the seeds are drawn from:

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\SecretRolls\SecretRolls;

$rolls = new SecretRolls(
    $store,                              // Contract\SecretRollStore: the journals and the sealed rolls
    $publisher,                          // Contract\JournalPublisher: Store\NoPublisher by default
    new DiceRoller(),                    // the notation, the dialects and the limits of the dice
    new Randomizer(new Secure()),        // the source of the seeds: the system's secure source by default
);
```

Pass the same `DiceRoller` — the same limits — to `RollVerifier`, so that what the game master rolls, the verifier
reads. `SecretRolls` and `RollVerifier` keep nothing between two calls, so one instance serves every request of a
worker; `InMemorySecretRollStore` keeps everything in the process, for tests and scripts.
