<!-- phil-dev source-sha256=f5f944a7cc15f5a54ccca33ba5774ac175fb827b799e0b8b3242726fe3105815 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : calendriers à travers
les changements d'heure, 29 février et règles annuelles, saisons et événements avec leurs limites, questions d'un
moment, lecteur et passerelles. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

`tests/Fuzz/seasons.php` est la cible de fuzzing des définitions, lancée par `composer fuzz` : tout texte JSON est
refusé avec l'exception de la bibliothèque ou donne des saisons qui, écrites puis relues, sont identiques.
`tests/Benchmark` mesure un instantané et les événements annoncés sur un catalogue de cent saisons.

## Tester le code qui lit les saisons

Donnez à `SeasonalEvents` un `InMemorySeasonCatalogue` et un `MockClock` réglé dans une saison ou hors d'une saison, ou
passez le moment à chaque appel. Le code qui dépend de `ActiveContent` reçoit une doublure du port.
