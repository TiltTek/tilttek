<!-- phil-dev source-sha256=ea0319d8f0d89970f31af1684f0372cf4fc3ca2170084c74cb08efd98c92a058 -->
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
);
```

`Sets` ne garde rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*.
`InMemoryDefinitionStore` convient aux ensembles déclarés dans la configuration de l'application et définis au
démarrage ; `InMemoryCompletionStore` garde tout dans le processus, pour les tests et les scripts.
