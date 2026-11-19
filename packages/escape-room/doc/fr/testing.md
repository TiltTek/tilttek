<!-- phil-dev source-sha256=ac7109e91360af18173b8edc0cb5af4afec90b13c7421d408f227cfc142b0122 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des salles et des chaînes, refusées à
chaque borne ; une partie jouée jusqu'à son évasion avec des indices demandés et envoyés, un saut, et le temps final ;
des pauses, une pause à la fin, le temps écoulé refusé puis expiré, les pauses comptées dans la fin ; l'écran
d'indices ; le consentement et les résultats ; chaque coup d'une partie terminée refusé ; des parties écrites et relues,
et refusées champ par champ ; des séances réservées privées et partagées, chaque raison de refus, les réservations qui
refusent en dernier ; les mêmes scénarios de `Contract\SessionReservations` exécutés sur le magasin en mémoire et sur
`Reservation\BookableSessionReservations`, dont les règles de `phil/reservation` sont dites comme des refus ; le classement par consentement, les égalités et les limites. Dans le monodépôt, Infection
maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/game.php` est la cible de fuzzing d'une partie stockée : tout JSON donne une partie de la salle qui s'écrit
comme elle a été lue, ou est refusé avec `InvalidRoom`, jamais une autre exception. `composer fuzz` l'exécute.

`tests/Benchmark/EscapeRoomsBench.php` mesure une proposition et la minuterie d'une partie de 50 énigmes enchaînées dont
49 sont résolues et 98 indices révélés, une partie stockée relue, et le classement de 10 000 résultats.

## Tester une application

Donnez aux services une horloge qui ne bouge pas, et des stockages en mémoire :

```php
$clock = new MockClock('2026-11-13 20:00 UTC');
$rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(10, 60, $clock), $dispatcher, $clock), $dispatcher, $clock);
$booker = new SessionBooker(new InMemorySessionReservations(), $dispatcher, $clock);
```

`$clock->sleep(3_600)` mène la minuterie jusqu'à sa fin.
