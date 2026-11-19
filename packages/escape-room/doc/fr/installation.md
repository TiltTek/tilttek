<!-- phil-dev source-sha256=ca40239b6c526eed93ffda631921da22172a163c5b0457090db8f4e1dad8a31d -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `tilttek/puzzle-builder` 0.1, le moteur d'énigmes des salles, avec ses propres prérequis.
- `phil/resource` 0.1, dont `ResourceReference` nomme une équipe, un groupe et un animateur.
- `psr/clock` et `symfony/clock`, pour la minuterie ; `psr/event-dispatcher`, pour les événements.
- `phil/reservation` 0.1, suggéré, pour `Reservation\BookableSessionReservations` : les séances réservées comme des
  intervalles d'une ressource réservable, avec ses heures d'ouverture, son préavis et sa fenêtre de réservation.

## Installer

```sh
composer require tilttek/escape-room
```

## Brancher la bibliothèque

`EscapeRooms` prend un `PuzzleEngine`, et facultativement un répartiteur d'événements et une horloge ;
`Booking\SessionBooker` prend les réservations de l'application, et facultativement le même répartiteur et la même
horloge :

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

$clock = new NativeClock();
$puzzles = new PuzzleEngine(new RateLimiterGuessLimiter($guessLimiterFactory), $dispatcher, $clock);

$rooms = new EscapeRooms(
    $puzzles,      // Tilttek\PuzzleBuilder\PuzzleEngine: the guesses, hints and puzzles of each game
    $dispatcher,   // Psr\EventDispatcher\EventDispatcherInterface, or null to dispatch nothing
    $clock,        // Psr\Clock\ClockInterface: the countdown, the same clock as the engine's
);

$booker = new SessionBooker(
    $reservations, // Contract\SessionReservations: the application's reservations of the sessions
    $dispatcher,
    $clock,        // when a session has started and is no longer booked
);
```

Les deux services ne gardent rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*.
