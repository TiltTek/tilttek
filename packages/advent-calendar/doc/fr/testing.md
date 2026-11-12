<!-- phil-dev source-sha256=b36b0c55d5b6fa4f63e1c7907f80a29fe7010b7c81613bbbb0c3f508eece58dc -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre les calendriers et leurs refus, les bornes de chaque jour dans le fuseau horaire du calendrier — la
première et la dernière seconde, les jours de 25 et de 23 heures quand l'heure change, un minuit qu'un changement d'heure
saute —, les trois politiques d'ouverture et la fermeture, les surprises, les ouvertures idempotentes et une requête qui
perd une course, les cases telles qu'un membre les voit, l'effacement, et le magasin en mémoire par
`OpeningStoreTestCase`, le contrat que réussit tout magasin. `tests/Benchmark/BoxesBench.php` mesure les cases qu'une
page lit, sur des calendriers de 24 et de 92 cases. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des
mutants couverts.

## Tester du code qui ouvre des cases

Construisez `AdventCalendar` avec le magasin en mémoire, une horloge que vous déplacez —
`Symfony\Component\Clock\MockClock` — et un répartiteur qui note ses événements :

```php
$clock = new MockClock('2026-12-03 23:59:59', 'Europe/Paris');
$advent = new AdventCalendar(new InMemoryOpeningStore(), $clock, $dispatcher);

self::assertTrue($advent->open($calendar, $member, 3)->first);

$clock->modify('2026-12-04 00:00:00');
self::assertSame(BoxState::Missed, $advent->box($sameDayCalendar, $member, 3)->state);
```

Réglez l'horloge dans le fuseau horaire du calendrier, ou donnez le décalage du moment, pour tester les bornes d'un
jour.
