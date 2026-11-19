<!-- phil-dev source-sha256=4e09f8b3d2dacc6aff9eea893f1b200e14ab7cb48fcbb7965937431e2ee7b758 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des tirages avec et sans enjeu de
l'ouverture à la preuve, chaque refus, une chance par participant, la ronde de la balise fixée après la marge et
attendue, des participants changés après la fermeture, des tirages et des preuves écrits et relus avec tout le reste
refusé, chaque défaut qu'un vérificateur trouve, le mélange comparé à des tirages calculés par une implémentation
distincte en Python, et l'adaptateur drand face à des relais simulés avec le `MockHttpClient` de Symfony — d'accord, en
désaccord, en retard, silencieux, trop longs, brisés ou menteurs —, le résultat de chacun signalé, la lecture entière arrêtée par l'horloge, et un podium avec
substituts dont les places font partie de la preuve. Dans le monodépôt, Infection maintient la
bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/proof.php` est la cible de fuzzing d'une preuve publiée : n'importe quel texte donne une liste de défauts,
jamais une exception. `tests/Fuzz/drand.php` est celle des réponses d'un relais : n'importe quel texte est refusé avec
`BeaconRefused` ou lu comme une chaîne ou une ronde. `composer fuzz` lance les deux.

`tests/Benchmark/RaffleBench.php` mesure une inscription, le tirage de 20 noms parmi 100 000 participants, et la preuve
d'un tirage de 10 000 participants lue et vérifiée en entier.

## Tester une application

Donnez à `Raffles` une horloge que vous déplacez et un générateur aléatoire à germe fixe, ainsi qu'un
`RandomnessBeacon` de votre cru qui renvoie des rondes que vous construisez : un `Beacon\Beacon` accepte toute
signature dont le SHA-256 est l'aléa que vous donnez.

```php
$raffles = new Raffles(new InMemoryRaffleStore(), $fakeBeacon, clock: $clock, secure: new Randomizer(new Xoshiro256StarStar(7)));
```

Vérifiez ensuite la preuve que l'application publie : `self::assertSame([], new RaffleVerifier()->problems($proof));`.
