<!-- phil-dev source-sha256=8478f4b7495926a08e5f4134362139400789b177e972d6860e45ddf90606ffe6 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les documents et leurs erreurs, les entrées et le catalogue, chaque fonction et chaque filtre des
gabarits, les vérifications du bac à sable et des noms, les grammaires, les cartes, les tirages, les germes et les
limites, et elle charge les exemples du paquet. Dans le monodépôt, Infection tient la bibliothèque à 100 % de mutants
couverts.

Les tests scénarisent le hasard avec un moteur qui retourne les nombres qu'on lui donne : `Randomizer::getInt($min,
$max)` retourne alors `$min` plus chaque nombre, ce qui permet à un test de dire quelle entrée prend chaque tirage. Une
horloge qui avance d'un pas fixe à chaque lecture teste la limite de temps.

## Bancs d'essai

`GeneratorBench` tire 1 et 100 résultats, uniques ou non, de listes de 10 et de 10 000 entrées filtrées sur une
étiquette, et construit un générateur sur ces catalogues. Un tirage dans une longue liste coûte autant que dans une
courte, une fois la liste filtrée sur ses étiquettes. À partir de la racine du monodépôt :

```sh
composer bench -- packages/tilttek-generator/tests/Benchmark
```

## Tester du code qui fait des tirages

Passez un `Randomizer` doté d'un germe, ou faites un tirage avec germe, et comparez-le à un second tirage du même
germe :

```php
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;

$generator = new Generator($catalog, randomizer: new Randomizer(new Xoshiro256StarStar(42)));
self::assertEquals($generator->draw('inn', new Draw(seed: 'x')), $generator->draw('inn', new Draw(seed: 'x')));
```

Construire le `Generator` dans un test vérifie le catalogue : un test qui le construit sur les fichiers de
l'application échoue sur un gabarit qui ne compile pas ou qui nomme une liste inexistante.
