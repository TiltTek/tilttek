# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The suite covers every construct of the notation and its errors, the validators, the canonical form and its round
trip, the limits, the rolls die by die with every modifier, the integer arithmetic in every rounding mode, custom dice,
the verifiable and replayable rolls, and the dialects. In the monorepo, Infection
holds the library at 100 % of covered mutants.

The tests script the dice with an engine that returns the numbers it is given: `Randomizer::getInt($min, $max)` then
returns `$min` plus each number, which lets a test say which face each die shows.

## Benchmarks

`DiceRollerBench` parses and rolls a plain roll, kept and exploding dice, a pool of several expressions, an expression
of modifiers — repeated rerolls, compound explosions, floors, failures, occurrences, a count rolled first, a sort —, and
a thousand dice. From the monorepo root:

```sh
composer bench -- packages/tilttek-dice/tests/Benchmark
```

## Fuzzing

`tests/Fuzz/dice-parser.php` hands the parser inputs nobody wrote: each one parses or is refused with a
`DiceException`, the canonical form of what parses parses back to itself, and the pool rolls with a seeded randomizer
or is refused. Its seeds are in `tests/Fuzz/corpus/dice-parser`. `tests/Fuzz/rolisteam-reader.php` does the same for
the reader of DiceParser's notation: each command reads or is refused with a `DiceException` placed within the command,
what the `diceparser` dialect writes whole reads back to the same text, and the pool rolls or is refused; its seeds are
in `tests/Fuzz/corpus/rolisteam-reader`. From the monorepo root:

```sh
composer fuzz -- dice-parser
composer fuzz -- rolisteam-reader
```

## Testing code that rolls dice

Pass a seeded `Randomizer` — `new Randomizer(new Mt19937(42))` — and compare with a second roll of the same seed, or
script the engine as the library's tests do:

```php
use Random\Engine;

final class ScriptedEngine implements Engine
{
    public function __construct(private array $numbers) {}

    public function generate(): string
    {
        return pack('V', array_shift($this->numbers));
    }
}
```
