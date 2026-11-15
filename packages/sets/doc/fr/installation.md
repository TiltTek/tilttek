<!-- phil-dev source-sha256=5b42f4d54d5f18be3e88b4dbc9ab0076b2815ab41ca92dfc5755101d9a1d7cce -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource` 0.1, dont `ResourceReference` nomme un détenteur.
- `psr/clock` et `psr/event-dispatcher` : l'horloge des complétions et le répartiteur des événements, tous deux
  facultatifs.

## Installer

```sh
composer require tilttek/sets
```

## Câbler la bibliothèque

```php
use Tilttek\Sets\Sets;

$sets = new Sets(
    $definitions,   // Contract\DefinitionStore: every version of every set
    $completions,   // Contract\CompletionStore: the completions, one per set, tier and holder
    $clock,         // a PSR-20 clock; UtcClock, the system's in UTC, by default
    $dispatcher,    // a PSR-14 dispatcher, told of each completion; null to dispatch nothing
    $backoff,       // a DeliveryBackoff for deliverPending(); one minute doubled up to a day, by default
);
```

`Sets` ne garde rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*.
`InMemoryDefinitionStore` convient aux ensembles déclarés dans la configuration de l'application et définis au
démarrage ; `InMemoryCompletionStore` garde tout dans le processus, pour les tests et les scripts.

## Reprendre les remises

Une complétion dont le gestionnaire d'événement a levé une exception reste non remise jusqu'à ce qu'elle soit annoncée
de nouveau (voir [Remise](progress-and-completions.md#remise)). `Sets::deliverPending()` annonce de nouveau ces
complétions sans attendre que les possessions de leurs détenteurs changent ; une commande ou une tâche planifiée de
l'application l'appelle à intervalles. Le stockage de complétions implémente aussi
[`Contract\PendingDeliveries`](extension-points.md#implémenter-un-stockage) ; `Store\InMemoryCompletionStore` le fait.

La bibliothèque ne dépend d'aucun cadriciel. Dans une application Symfony, `tilttek/sets-bundle` enregistre `Sets`
depuis sa configuration, avec la commande `tilttek:sets:deliver`, le message `DeliverPendingCompletions` et son
*handler*, et le planificateur `tilttek_sets`.
