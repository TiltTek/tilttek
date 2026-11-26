<!-- phil-dev source-sha256=a7f0d41fb734991d8f8f9c9e67f17fb2beb6d1753bd6128492effa8e9fb175b2 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/points`, le grand livre où les points sont écrits.
- `phil/resource`, dont les références désignent les membres.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/predictions
```

## Câbler la bibliothèque

`Predictions` reçoit le magasin, les points et l'horloge ; tout le reste a une valeur par défaut :

```php
use Tilttek\Predictions\Predictions;

$predictions = new Predictions(
    $store,         // Contract\PredictionStore; Store\InMemoryPredictionStore for tests
    $points,        // Tilttek\Points\Points
    $clock,         // Psr\Clock\ClockInterface
    'stars',        // the currency of tilttek/points; its default when null
    'predictions',  // the issuance account the points come from
);
```

`Predictions` ne garde aucun état entre deux appels : une seule instance sert toutes les requêtes, en mode *worker*
comme en mode requête. Le magasin qu'elle reçoit tient aussi cette promesse.

## Le magasin

`Store\InMemoryPredictionStore` ne conserve rien au-delà du processus : il sert aux tests. Une application conserve ses
questions et ses prédictions dans sa base de données, par son propre `Contract\PredictionStore`, dont le contrat est
décrit dans [Points d'extension](extension-points.md).
