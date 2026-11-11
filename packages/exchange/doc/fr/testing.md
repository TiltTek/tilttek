<!-- phil-dev source-sha256=43a4ab686dd48d4044a3f54fd30b5faca61015b67a11fc56ffa1cd0783ddd439 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre chaque valeur et ses refus, les stockages en mémoire par les cas de test des contrats, `Redemptions`
avec le grand livre de `tilttek/points`, les gardes dans différents fuseaux horaires, `Transfers`, `Trades` avec un
inventaire en mémoire, des stockages qui perdent une course contre un autre processus, et l'effacement. Dans le
monodépôt, Infection tient la bibliothèque à 100 % de mutants couverts.

## Tester du code qui échange

Construisez les services sur les stockages en mémoire, un grand livre en mémoire et une horloge que vous contrôlez,
appelez le code à tester, puis lisez les soldes et les stockages :

```php
$clock = new MockClock('2026-11-07 12:00:00');
$currencies = new Currencies(new Currency('points'));
$points = new Points(new InMemoryLedger(), $clock, $currencies);
$redemptions = new Redemptions(new InMemoryCatalog(new Reward('seat', 250, stock: 1)), new InMemoryReservationStore(), $points, $currencies, $clock);
$points->grant($member, 300, 'welcome');

$checkout = new BookSeat($redemptions);
$checkout($member, 'order:1');
$checkout($member, 'order:1'); // a handler run twice

self::assertSame(50, $points->balance($member));
self::assertSame(0, $redemptions->remaining('seat'));
```

Avancez l'horloge au-delà de la durée de vie et appelez `expireDue()` pour tester ce qui arrive à une réservation non
confirmée. Un `Announcer` à vous enregistre les événements.
