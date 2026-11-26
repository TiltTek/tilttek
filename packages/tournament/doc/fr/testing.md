<!-- phil-dev source-sha256=7a592ba7251ee03af8a5385d066290a68053b78e2dc3cb1de6315db25fac829e -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les déclarations et
leurs refus, les résultats et le pointage, l'appariement suisse et les deux algorithmes de jumelage, les tables de Berger
et les poules, le tableau et son état, les bris d'égalité et le classement, le classement initial, et le service du
début d'un tournoi jusqu'à son podium, avec conflits, corrections, retraits, calendriers et explications. De petits
tournois écrits à la main vérifient les appariements, les côtés et le classement table par table.
`Seeding\RatingSeeding` est testé là où `tilttek/rating` est installé, et ignoré sinon. Dans le monodépôt, Infection
maintient la bibliothèque à 100 % des mutants couverts.

`tests/Unit/PropertyTest.php` joue des tournois entiers avec des résultats tirés de graines fixes, de 4 à 64 participants :
une étape suisse ne répète jamais une rencontre ou une exemption qu'un autre appariement de la ronde évite, et ne le
fait jamais du tout quand le champ compte deux fois plus de participants que de rondes ; la même graine joue le même
tournoi ; chaque paire d'une poule se rencontre une fois par tour, avec les côtés échangés au match retour et les
repos là où la poule est impaire ; et un tableau se termine avec un seul champion, ses exemptions à la première ronde
seulement.

`tests/Benchmark/PairingBench.php` mesure une ronde suisse de 16 participants appariée exactement, de 64 et de 512 par
la recherche bornée, la table de Berger d'une poule de 20, et le classement de 512 participants avec trois bris
d'égalité.

## Tester du code qui mène des tournois

Donnez à `Tournaments` un `InMemoryTournamentStore` et un `FixedSeeding`, pour que le premier ordre soit celui qu'écrit
le test :

```php
$tournaments = new Tournaments(new InMemoryTournamentStore(), seeding: FixedSeeding::inOrder(['a', 'b', 'c', 'd']));
$tournaments->start(new Tournament('cup', 'seed', ['a', 'b', 'c', 'd'], [Stage::swiss('swiss', 3)]));
```
