<!-- phil-dev source-sha256=ade74f5eec102514c7c3cb970d02988a8f7a5403e895cd981fec803b043b57a8 -->
# Installation

```sh
composer require tilttek/conditions
```

Le paquet exige PHP 8.5 ou une version plus récente et `psr/clock`, dont l'horloge donne l'heure au service.

Une application donne à `Conditions` :

- un `Contract\ConditionStore` qui garde les conditions de chaque personnage dans sa base de données ;
- le `ConditionCatalogue` de son système de jeu, construit une fois depuis les données de son système ;
- une `ClockInterface` PSR-20, comme la `NativeClock` de `symfony/clock` ;
- un `Contract\ConditionObserver`, pour envoyer ses messages, ou rien ;
- un `Effect\EffectCalculator` avec l'arrondi de son système, ou celui par défaut, qui arrondit vers le bas.

Le service, le catalogue et le calculateur ne gardent aucun état entre les appels : une seule instance de chacun sert
toutes les requêtes d'un worker de longue durée.

`Store\InMemoryConditionStore` garde les conditions en mémoire, pour les tests.
