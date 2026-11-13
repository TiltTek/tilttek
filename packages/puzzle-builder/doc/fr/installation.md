<!-- phil-dev source-sha256=c7aee7ee81ad9987bb04a88ca10b849b6bfe167db32ecf40a858c5dabc564fe5 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `intl` pour la normalisation des réponses et `mbstring`.
- `phil/resource` 0.1, dont `ResourceReference` nomme une équipe.
- `psr/clock` et `symfony/clock`, pour l'heure de chaque coup ; `psr/event-dispatcher`, pour les événements.
- `symfony/rate-limiter`, suggéré, pour `Limiter\RateLimiterGuessLimiter`.

## Installer

```sh
composer require tilttek/puzzle-builder
composer require symfony/rate-limiter   # to count the guesses with a limiter of the component
```

## Brancher la bibliothèque

`PuzzleEngine` prend le limiteur de propositions, et facultativement un répartiteur d'événements et une horloge :

```php
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$guesses = new RateLimiterFactory(
    ['id' => 'puzzle_guesses', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 minute'],
    new CacheStorage($cachePool),   // a store every worker reads
);

$engine = new PuzzleEngine(
    new RateLimiterGuessLimiter($guesses),   // Contract\GuessLimiter: the guesses of a team on a puzzle in a period
    $dispatcher,                             // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    new NativeClock(),                       // Psr\Clock\ClockInterface: the time of each move, written in UTC
);
```

Dans une application Symfony, la fabrique est le limiteur déclaré sous `framework.rate_limiter`, injecté par son nom.

`PuzzleEngine` ne garde rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*. Le limiteur
est obligatoire : il n'y a pas de moteur sans borne sur les propositions. `Limiter\InMemoryGuessLimiter` compte dans la
mémoire du processus, pour les tests et pour une partie jouée dans un seul processus.
