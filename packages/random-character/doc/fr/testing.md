<!-- phil-dev source-sha256=6a04b431a240162ef24073c33f95f53b34c496f8c879c58999f35a048c70880a -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : statistiques, champs, règles et catalogues construits et
refusés règle par règle à leurs limites ; marqueurs remplacés par des textes, des nombres et des valeurs de résultats
structurés, et refusés quand une valeur ne peut pas nommer un générateur ; règles vérifiées contre un système aux
attributs bornés et non bornés et contre les générateurs ; personnages tirés par chaque méthode, la même graine donnant le
même personnage, chaque partie gardant sa propre graine quand les règles grandissent, graines tirées et refusées, bornes,
générateurs et dés défaillants, attributions données une seule fois, et événements communiqués ; modifications et leurs
refus ; le lecteur avec l'exemple de `examples/`, YAML comme JSON, et chaque refus à son emplacement. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/document.php` est la cible de fuzzing du lecteur : tout texte YAML est refusé avec les exceptions de la
bibliothèque ou donne des règles dont chaque champ se retrouve par sa clé et ne nomme que des champs qui le précèdent.
`composer fuzz` l'exécute.

`tests/Benchmark/RollBench.php` mesure un personnage de six attributs et cinq champs tiré avec une graine tirée et avec
une graine donnée, ainsi que la lecture de 100 jeux de règles en YAML.

## Tester du code qui tire des personnages

Donnez au *roller* un `Randomizer` fixe, ou donnez une graine à chaque tirage, pour que le même test donne le même
personnage :

```php
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\RandomCharacter\CharacterRoller;

$roller = new CharacterRoller($systems, $generator, randomizer: new Randomizer(new Mt19937(42)));

$character = $roller->roll($rules, 'test');
```

Une liste ou un générateur à une seule entrée donne à un champ une valeur connue. Un `Contract\CharacterObserver` de
votre cru, gardé dans le test, recueille les événements.
