# Installation

## Requirements

- PHP 8.5 or later.
- The `ctype` and `mbstring` extensions, which the lexer and the limits use, and `sodium`, whose `sodium_bin2hex()`
  writes seeds and identifiers in constant time.

The library has no Composer dependency.

## Installing

```sh
composer require tilttek/dice
```

Nothing is registered and nothing is configured: build a `DiceRoller`, or the parts you need, where you use them. A
Symfony application installs `tilttek/dice-bundle`, which builds them from its configuration.

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;

$dice = new DiceRoller(new Limits(dice: 50, sides: 100));
```
