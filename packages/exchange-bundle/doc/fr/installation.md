<!-- phil-dev source-sha256=236a5462c39807b9b0574f35a315224108ea53f3c1c450da34709610b613f4fb -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.6.
- `tilttek/exchange`, les échanges eux-mêmes, et `tilttek/points`, le registre sur lequel ils déplacent des points.
- Les services `Points` et `Currencies`, que `tilttek/points-bundle` enregistre ; gardez son registre sur la même
  connexion que les échanges, pour que les points d'un échange bougent dans la transaction de l'échange.
- `psr/event-dispatcher` ; FrameworkBundle fournit le répartiteur.
- Facultatif : `doctrine/orm` pour l'écouteur de schéma, `symfony/console` pour les commandes, `symfony/form` pour les
  formulaires, `phil/privacy-bundle` pour l'exportation et l'effacement.

## Activer le bundle

```sh
composer require tilttek/points-bundle tilttek/exchange-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\PointsBundle\TilttekPointsBundle::class => ['all' => true],
    Tilttek\ExchangeBundle\TilttekExchangeBundle::class => ['all' => true],
];
```

Configurez les récompenses ([Configuration](configuration.md)), puis laissez Doctrine écrire la migration des tables :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Faites lancer `bin/console tilttek:exchange:expire` par un planificateur toutes les quelques minutes, pour que les
réservations non confirmées rendent leurs points et que les offres sans réponse libèrent leurs objets.
