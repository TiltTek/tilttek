<!-- phil-dev source-sha256=3e799664f049abb2511a3aba93d30116b1f40884f14ac4ff6a4a5a42d9e84396 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : Elo et OpenSkill face à des valeurs
calculées par une implémentation distincte en Python à partir des formules publiées — des duels, des parties de plusieurs
joueurs avec égalités, des parties par équipes, des nulles, des surprises loin dans la queue, le facteur K palier par
palier, l'échelle, le plancher —, le duel de deux nouveaux joueurs que donne la méthode de la publication, la loi
normale face à des valeurs de référence, des résultats et des cotes stockées écrits et relus avec tout le reste refusé,
les cotes privées et le classement, les conflits, les événements et les parties avant une célébration, et des relectures
qui redonnent exactement les cotes enregistrées, y compris pour des membres nommés par des nombres. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/rating.php` est la cible de fuzzing du calcul d'une cote : n'importe quel texte, lu comme un résultat en
JSON, est refusé par `InvalidRating` ou coté par chaque système à partir de cotes tirées du même texte, chaque nouvelle
cote se relisant à l'identique. `composer fuzz` la lance.

`tests/Benchmark/RatingBench.php` mesure une relecture de 10 000 résultats parmi 500 membres — des duels, des parties à
quatre et des parties de deux équipes de deux — par Elo, OpenSkill Plackett-Luce et OpenSkill Thurstone-Mosteller.

## Tester une application

Donnez à `Ratings` le dépôt de stockage en mémoire, une source de résultats de votre cru et un répartiteur qui
enregistre les événements :

```php
$ratings = new Ratings(new InMemoryRatingStore(), new EloSystem(), $recordingDispatcher, $source);
$ratings->record(GameResult::duel('r1', 'chess', new DateTimeImmutable('2026-11-13 20:00 UTC'), 'ann', 'bob'));
self::assertSame(151_600, $ratings->rating('chess', 'ann')->score);
```

Les valeurs sont des nombres entiers : un test les compare exactement.
