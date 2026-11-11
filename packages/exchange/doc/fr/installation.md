<!-- phil-dev source-sha256=16e83b5300177b7b55afbe88bdb171fa0bda94b11202b819c4db99f2b49dfe6a -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- `tilttek/points`, le grand livre sur lequel les récompenses et les transferts déplacent les points, et les devises
  des prix.
- `phil/resource`, les références opaques des membres (`member:44`) et des objets (`card:812`).
- `psr/clock`, l'interface de l'horloge qui horodate chaque échange ; toute horloge PSR-20 convient, comme
  `symfony/clock`.

## Installer

```sh
composer require tilttek/exchange
```

Rien n'est enregistré et rien n'est configuré : construisez `Redemptions`, `Transfers` et `Trades` là où vous les
utilisez, sur les stockages et le `Points` de `tilttek/points`. Une application Symfony installe
`tilttek/exchange-bundle`, qui garde les stockages dans Doctrine DBAL et construit les services à partir de sa
configuration.

```php
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfers;

$redemptions = new Redemptions(InMemoryCatalog::fromArray(['discount-10' => ['price' => 100]]), new InMemoryReservationStore(), $points, $currencies, $clock);
$transfers = new Transfers(new InMemoryTransferLog(), $points, $currencies, $clock, new Guards());
$trades = new Trades(new InMemoryOfferStore(), $inventory, $points, $currencies, $clock, new Guards());
```

Les stockages en mémoire oublient tout avec le processus : ils servent aux tests, aux scripts et aux exemples. Une
application qui garde ses échanges écrit des stockages sur son propre support
([Écrire un stockage](writing-a-store.md)) ou installe le bundle.
