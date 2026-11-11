<!-- phil-dev source-sha256=3d0d458cec122b2eb01632fea888277d85916f2763101799d8747671dc2449aa -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite couvre chaque valeur et ses refus, le registre en mémoire par `LedgerTestCase` (le contrat que tout registre
respecte), `Points`, les lots et l'expiration, les règles et leurs périodes à travers les fuseaux horaires, et la
projection avec le journal de `tilttek/activity` : plafonds, annulations et reconstructions. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui déplace des points

Construisez `Points` sur un `InMemoryLedger` avec une horloge que vous contrôlez, appelez le code testé, puis lisez le
solde :

```php
$points = new Points(new InMemoryLedger(), new MockClock('2026-11-07 12:00:00'), new Currencies(new Currency('points')));

$checkout = new RedeemAtCheckout($points);
$checkout($order);
$checkout($order); // a handler run twice

self::assertSame(20, $points->balance($member));
```

Les règles de gain se testent par le journal : enregistrez des activités dans un `Journal` sur un
`InMemoryActivityLog` avec `LiveProjections` autour d'une `PointsProjection`, puis lisez les soldes.
