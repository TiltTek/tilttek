# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network: stats, fields, rules and catalogues built and refused rule
by rule at their limits; placeholders replaced by texts, numbers and values of structured results, and refused when a
value cannot name a generator; rules checked against a system with bounded and unbounded attributes and against the
generators; characters rolled by each method, the same seed giving the same character, each part keeping its own seed
when the rules grow, seeds drawn and refused, bounds, failing generators and dice, attributions given once, and the
events told; edits and their refusals; the reader with the example of `examples/`, YAML and JSON alike, and every
refusal at its place. In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/document.php` is the fuzz target of the reader: any YAML text is refused with the library's exceptions or
gives rules whose every field is found again by its key and names only fields before it. `composer fuzz` runs it.

`tests/Benchmark/RollBench.php` measures a character of six attributes and five fields rolled with a drawn seed and
with a given one, and reading 100 rules from YAML.

## Testing code that rolls characters

Give the roller a fixed `Randomizer`, or give each roll a seed, so that the same test gives the same character:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\RandomCharacter\CharacterRoller;

$roller = new CharacterRoller($systems, $generator, randomizer: new Randomizer(new Mt19937(42)));

$character = $roller->roll($rules, 'test');
```

A list or a generator with a single entry gives a field a known value. A `Contract\CharacterObserver` of your own, kept
in the test, collects the events.
