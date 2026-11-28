<!-- phil-dev source-sha256=be8510e8edaec633b8a536d30d2bdf4ece5fca121809666eaafa3c2213288618 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : des plages lues, imprimées et refusées ; des tables
construites et refusées règle par règle, à leurs limites de 32 colonnes et de 1 000 lignes ; des consultations par clé
et par plage dans n'importe quel ordre de lignes, les bornes et les trous ; des cellules de chaque type et leurs
mauvaises lectures ; le catalogue par système et par catégorie, avec chaque source une fois et l'attribution de
`tilttek/rpg-systems` ; des lancers sur un `Randomizer` à germe fixe, des modificateurs, la borne aux extrémités, des dés
entrés, des cellules lancées et les événements annoncés ; des recherches entre locales, casse et accents, et leurs
bornes ; le lecteur avec l'exemple de `examples/`, YAML comme JSON, et chaque refus à son emplacement. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/document.php` est la cible de fuzzing du lecteur : tout texte YAML est refusé avec les exceptions de la
bibliothèque ou donne des tables dont chaque ligne se retrouve par sa clé ou par sa plage. `tests/Fuzz/range.php` est la
cible de fuzzing des plages : tout texte est refusé ou donne une plage dont la forme imprimée se relit comme la même
plage. `composer fuzz` les exécute.

`tests/Benchmark/LookupBench.php` mesure une consultation par plage dans une table de 1 000 lignes, une consultation par
clé, un lancer, une recherche dans 50 tables de 100 lignes, et la lecture de ces tables depuis du YAML.

## Tester du code qui lit des tables

Lisez un petit document de votre cru et lancez avec un `Randomizer` à germe fixe, pour que le même test donne la même
ligne :

```php
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Rolling\TableRoller;

$tables = new TableReader()->catalogue([
    'format' => TableReader::FORMAT,
    'source' => ['title' => 'Test', 'licence' => 'owned'],
    'tables' => [[
        'key' => 'coin',
        'names' => 'Coin',
        'die' => '1d2',
        'columns' => [['key' => 'side', 'names' => 'Side']],
        'rows' => [['range' => '1', 'side' => 'Heads'], ['range' => '2', 'side' => 'Tails']],
    ]],
]);

$roll = new TableRoller()->roll($tables->get('coin'), new Randomizer(new Mt19937(42)));
```

Pour tester une ligne sans dés, appelez `TableRoller::enter()` avec la valeur voulue. Un `Contract\TableObserver` de votre
cru, gardé dans le test, recueille les événements.
