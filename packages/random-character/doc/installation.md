# Installation

```sh
composer require tilttek/random-character
```

The library needs PHP 8.5 or later with the `mbstring` extension. It brings `tilttek/rpg-systems`, `tilttek/generator`,
`tilttek/dice` and `symfony/yaml`.

A first character, from rules built in code and a system read by `tilttek/rpg-systems`:

```php
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;
use Tilttek\RpgSystems\Format\SystemReader;

$systems = new SystemReader()->load('my-system.yaml');   // a system "mine" with the attributes str and dex
$rules = new CreationRules('mine.quick', 'mine', new Stats(StatMethod::Each, ['str', 'dex'], '3d6'), [
    Field::rolled('age', '2d6+15'),
]);

$character = new CharacterRoller($systems)->roll($rules, 'my first seed');
$character->toArray();   // rules, system, seed, stats, fields, attributions, edited
```

Rules that draw fields from generators need a `Generator` of `tilttek/generator`, passed to the `CharacterRoller`.

Read the documents once, when the application warms up or its cache is rebuilt, and keep the `RulesCatalogue`, the
`Systems` and the `Generator`: they are values and services that every request shares.
