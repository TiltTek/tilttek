<!-- phil-dev source-sha256=152a50fcf52c3477aa492a5bd300224dfb286d68a02dbaeb27d42ef1481a8c79 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- `tilttek/activity`, le journal de l'activité des membres que lisent les règles de gain.
- `phil/resource`, la référence opaque d'un membre (`member:44`).
- `psr/clock`, l'interface de l'horloge qui date chaque mouvement ; toute horloge PSR-20 convient, comme
  `symfony/clock`.

## Installer

```sh
composer require tilttek/points
```

Rien n'est enregistré et rien n'est configuré : construisez `Points` et `PointsProjection` sur un `Ledger` là où vous
les utilisez. Une application Symfony installe `tilttek/points-bundle`, qui garde le registre dans Doctrine DBAL et
construit les deux à partir de sa configuration.

```php
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Points\PointsProjection;
use Tilttek\Points\Rule\Rules;

$ledger = new InMemoryLedger();
$currencies = new Currencies(new Currency('points'), new Currency('stars', 'P1Y'));
$points = new Points($ledger, $clock, $currencies);
$projection = new PointsProjection($ledger, $currencies, Rules::fromArray([
    'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
], 'America/Toronto'), $clock);
```

`InMemoryLedger` oublie tout à la fin du processus : il sert aux tests, aux scripts et aux exemples. Une application
qui garde ses points écrit un `Ledger` sur son stockage ([Écrire un registre](writing-a-ledger.md)) ou installe le
bundle.
