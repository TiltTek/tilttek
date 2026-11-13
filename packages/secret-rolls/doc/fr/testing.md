<!-- phil-dev source-sha256=afae53abff4ccdc1e09be63f6d431daf1c6a1b9aec08c9032a34c8ff5bd303b4 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des jets scellés et engagés à partir
d'un générateur aléatoire à germe fixe, des résultats relancés à partir de leur germe, des révélations dans l'ordre et
une seule fois, des révélations partielles et totales à la clôture, des jets retenus révélés après la scène, chaque
refus, des ouvertures et des entrées écrites et relues avec tout le reste refusé, et des journaux vérifiés défaut par
défaut — une entrée déplacée, modifiée, hors de sa scène, un jet engagé deux fois ou après la clôture, une révélation
qui ne correspond pas. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/opening.php` est la cible de fuzzing de la vérification d'un jet révélé : n'importe quel texte JSON donne
vrai ou faux, jamais une exception. `tests/Fuzz/journal.php` est celle d'un journal publié : n'importe quel texte JSON
est refusé avec `InvalidSecretRoll` ou donne un journal dont la vérification liste les défauts. `composer fuzz` lance
les deux.

`tests/Benchmark/SecretRollsBench.php` mesure un jet engagé, un jet révélé vérifié, et l'export d'une scène de 500 jets,
tous révélés, lue et vérifiée en entier.

## Tester une application

Donnez à `SecretRolls` un générateur aléatoire à germe fixe, afin que ses germes soient les mêmes à chaque exécution :

```php
$rolls = new SecretRolls(new InMemorySecretRollStore(), secure: new Randomizer(new Xoshiro256StarStar(7)));
```

Vérifiez ensuite que le journal que l'application publie tient : `self::assertSame([], $journal->problems());`.
