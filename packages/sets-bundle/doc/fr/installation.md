<!-- phil-dev source-sha256=2e952051d30ca87d08616cf395e91243124f696b47dee2d0c54b29fe701b5f29 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent et Symfony 8.2.
- `tilttek/sets`, dont le bundle enregistre `Sets`.
- Facultatifs : `symfony/console` pour la commande `tilttek:sets:deliver`, `symfony/messenger` pour le *handler* du
  message `DeliverPendingCompletions`, `symfony/scheduler` pour le planificateur `tilttek_sets` et `symfony/lock` pour
  son verrou. Le bundle enregistre chacun seulement quand son paquet est installé.

## Installer

```sh
composer require tilttek/sets-bundle
composer require symfony/console symfony/messenger symfony/scheduler
```

## Enregistrer le bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\SetsBundle\TilttekSetsBundle::class => ['all' => true],
];
```

Sans configuration, les définitions et les complétions sont gardées en mémoire, ce qui convient aux tests. Une
application nomme ses stockages, comme le montre [Configuration](configuration.md) : le stockage de complétions
implémente aussi `Tilttek\Sets\Contract\PendingDeliveries`, dont la commande et le planificateur ont besoin.
