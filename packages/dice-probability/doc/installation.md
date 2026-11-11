# Installation

## Requirements

PHP 8.5 or later, and `tilttek/dice`, which reads the expressions. The library has no other dependency: its exact
numbers are its own, without the `bcmath` or `gmp` extensions.

## Installing

```sh
composer require tilttek/dice-probability
```

A Symfony application takes `tilttek/dice-probability-bundle`, which wires the library on the dice, notations and
limits of `tilttek/dice-bundle` and adds the Twig functions, the table, the chart and the Live Component.

## First use

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Probabilities;

$dice = new DiceRoller();
$probabilities = new Probabilities();

$distribution = $probabilities->distribution($dice->parse('4d6kh3'));
(string) $distribution->probability(18);   // "21/1296"
$distribution->mean()->toFloat();           // 12.2446…
```

When the expressions name custom dice, give `Probabilities` the same `CustomDice` as the `DiceRoller` that parses
them, and the same `Limits` — see [Distributions](distributions.md).
