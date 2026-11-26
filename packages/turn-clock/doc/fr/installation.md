<!-- phil-dev source-sha256=92c84753cf7401dfd53d535924af46f9573db8253b3c3cd73e2d6171aea02b96 -->
# Installation

```sh
composer require tilttek/turn-clock
```

Le paquet exige PHP 8.5 ou une version plus récente et `psr/clock`, dont l'horloge donne l'heure au service.

Une application donne à `TurnClock` :

- un `Contract\ClockStore` qui garde les horloges dans sa base de données ;
- une `ClockInterface` PSR-20, comme la `NativeClock` de `symfony/clock` ;
- un `Contract\ClockObserver`, pour envoyer ses messages, ou rien.

Le service ne garde aucun état entre les appels : une seule instance sert donc chaque requête d'un *worker* de longue
durée.

`Store\InMemoryClockStore` garde les horloges en mémoire, pour les tests.
