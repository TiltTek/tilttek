<!-- phil-dev source-sha256=ff2c756d87cea6906fe20b024766d7ca665fb117e43be406dc10134fc343f258 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : la valeur p contre les quantiles
publiés de la loi du khi carré et les formes closes à deux, quatre et six degrés ; des statistiques exactes sur de
petits décomptes dont la valeur p est e^(−x/2) ; le minimum de lancers en lancers entiers ; 2 000 dés équitables tirés
d'un germe fixe qui franchissent les seuils aussi rarement qu'ils le doivent, et un dé usé repéré ; chaque phrase en
français et en anglais ; les peines, les libérations, l'historique, les limites et les refus de la prison avec une
horloge *mock*. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/tally.php` est la cible de fuzzing du test : n'importe quels octets, lus comme des lancers, donnent une
valeur p entre 0 et 1 et une explication, ou un refus. `composer fuzz` l'exécute.

`tests/Benchmark/GoodnessOfFitBench.php` mesure 10 000 lancers d'un d20 comptés, testés et expliqués, ainsi que le test
d'un d100.

## Tester une application

Donnez au gardien une `Symfony\Component\Clock\MockClock` et avancez-la pour terminer une peine ; enregistrez des
lancers dans une `InMemoryRollSource` avec leur heure pour tester `since`.
