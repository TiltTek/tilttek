<!-- phil-dev source-sha256=2c330539e155835b1e22ddcc5285105f597da233e307fc8fc0da5adf71a8a73b -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les définitions et leurs bornes, les climats et leurs références, le lecteur et l'endroit de chaque
problème, les transitions et les températures écrites pour `tilttek/generator`, les fichiers de générateurs livrés
contre les climats livrés, et les prévisions : le même germe donne les mêmes jours, chaque jour suit le jour d'avant par
un voisin et de quelques degrés, les températures restent dans la plage de la saison, et une nouvelle saison est atteinte
jour après jour. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Bancs d'essai

`WeatherBench` mesure des prévisions de 1, 7 et 90 jours à partir des climats livrés, environ 0,2 ms par jour, et la
construction d'un `Weather`, environ 15 ms, qu'un processus fait une fois. Depuis la racine du monodépôt :

```sh
composer bench -- packages/tilttek-weather/tests/Benchmark
```

## Fuzzing

La cible de fuzzing `climates` lit n'importe quel texte YAML : il est refusé par une `InvalidClimate`, ou donne des
climats dont la météo fait la prévision de deux jours dans chaque saison de chaque zone. Depuis la racine du monodépôt,
`composer fuzz -- climates`.

## Tester du code qui fait des prévisions

Faites la prévision avec un germe, ou donnez au `Weather` un `Randomizer` à germe, et comparez avec une deuxième
prévision :

```php
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Weather\Weather;
use Tilttek\Weather\WeatherState;

$weather = new Weather(randomizer: new Randomizer(new Xoshiro256StarStar(42)));
$state = new WeatherState('temperate', 'spring');
self::assertEquals($weather->forecast($state, 3, 'x'), $weather->forecast($state, 3, 'x'));
```

Les climats propres à l'application sont vérifiés quand ils sont lus : un test qui lit le fichier de l'application
échoue sur un voisin ou une condition qui n'est pas déclaré.
