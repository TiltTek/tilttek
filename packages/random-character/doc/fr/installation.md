<!-- phil-dev source-sha256=6ed56847aca3cc57b309fd9e759154172413811180be98c59d2e9eb7b8fba34d -->
# Installation

```sh
composer require tilttek/random-character
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec l'extension `mbstring`. Elle apporte
`tilttek/rpg-systems`, `tilttek/generator`, `tilttek/dice` et `symfony/yaml`.

Un premier personnage, à partir de règles construites en code et d'un système lu par `tilttek/rpg-systems` :

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

Des règles qui tirent des champs de générateurs ont besoin d'un `Generator` de `tilttek/generator`, passé au
`CharacterRoller`.

Lisez les documents une seule fois, au réchauffement de l'application ou à la reconstruction de son cache, et gardez le
`RulesCatalogue`, les `Systems` et le `Generator` : ce sont des valeurs et des services que toutes les requêtes partagent.
