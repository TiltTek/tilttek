<!-- phil-dev source-sha256=30af7507ccb7dc2d71ee099d0748d20a533a4205c5ecb068cd61064c866b6609 -->
# Réservations

## Les séances

Une `Booking\Session` est une séance d'une salle : son identifiant dans les réservations de l'application, la salle,
son début et sa fin — gardés en UTC, la fin après le début — et la façon dont ses places se vendent :

| `SessionMode` | Places |
|---|---|
| `Private` | Un groupe réserve toute la salle |
| `Shared` | Des groupes réservent des places et jouent ensemble, jusqu'à la plus grande équipe de la salle |
| `Either` | La première réservation décide : une réservation privée prend toute la salle, une réservation de places la partage |

## Réserver

`Booking\SessionBooker::book()` réserve une séance pour un groupe — un membre ou une équipe, par un identifiant stable,
jamais une adresse courriel ni une référence du genre `erased`, que seul un effacement écrit — avec ses places, privée
ou non, sous un identifiant que choisit l'application :

```php
$booking = $booker->book($room, $session, ResourceReference::of('member', '42'), seats: 4, private: false, booking: 'b-01J8');
$booker->free($room, $session);   // the seats left: 2 in a room for 6
```

Les règles de la salle sont vérifiées d'après les réservations déjà faites ; un refus lance `BookingRefused`, dont la
`reason` est un `Booking\BookingRefusal` :

| Raison | Quand |
|---|---|
| `OtherRoom` | La séance est celle d'une autre salle |
| `Started` | La séance a commencé |
| `SharedOnly` | Une réservation privée d'une séance partagée |
| `PrivateOnly` | Une réservation de places dans une séance privée |
| `TooFewPlayers` | Un groupe privé plus petit que la plus petite équipe de la salle, ou aucune place |
| `TooManyPlayers` | Un groupe plus grand que la plus grande équipe de la salle |
| `AlreadyBooked` | Le groupe détient déjà une réservation de la séance |
| `Full` | Les places restantes, ou une réservation privée, ne laissent pas de place ; une réservation privée d'une séance déjà réservée |
| `Taken` | Les réservations l'ont refusée : une autre réservation a atteint la séance la première |
| `Closed` | Les réservations l'ont refusée : la salle n'est pas ouverte pendant toute la séance |
| `TooSoon` | Les réservations l'ont refusée : la séance commence plus tôt que ne le permet le préavis de la salle |
| `TooFarAhead` | Les réservations l'ont refusée : la séance commence plus loin que la salle ne prend de réservations |
| `OffSchedule` | Les réservations l'ont refusée : la séance ne respecte pas les durées ou les débuts que la salle accepte |

Une fois les règles passées, les réservations détiennent la réservation et vérifient de nouveau ; `Event\SeatsBooked`
est envoyé une fois qu'elles la détiennent, jamais pour une réservation refusée.

## Le port vers les réservations

`Contract\SessionReservations` est l'ensemble des réservations de l'application :

- `bookings($session)` liste les réservations de la séance, dans l'ordre où elles ont été faites ;
- `reserve($session, $booking, $capacity)` détient la réservation et donne null, ou donne le `BookingRefusal` des
  réservations : `Taken` quand les places ont été prises entre temps, ou l'une de leurs propres règles. Une
  réservation privée détient toute la capacité. Deux groupes qui réservent en même temps les dernières places ne les
  obtiennent pas tous les deux : l'implémentation vérifie de nouveau dans sa propre transaction ou son propre verrou.

`Store\InMemorySessionReservations` les garde en mémoire, pour les tests et les exemples ; `reset()` les oublie.

## Les réservations sur phil/reservation

`Reservation\BookableSessionReservations` implémente le port sur `phil/reservation`, que l'application installe :

```sh
composer require phil/reservation
```

Une salle est un `Bookable` dont la capacité est sa plus grande équipe, une séance un `Interval` de son début à sa fin.
Une réservation privée détient toute la capacité ; une réservation de places détient autant d'unités que de places.
Chaque réservation est vérifiée par `Bookable::check()` d'après les réservations de la séance, et gardée seulement
quand le `Bookable` l'accepte :

```php
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;

$reservations = new BookableSessionReservations(
    $ledger,  // Contract\SeatLedger: where the bookings are kept
    $rooms,   // Reservation\Contract\RoomBookables, or null for a Bookable with the capacity alone
    $clock,   // Psr\Clock\ClockInterface: when a booking is asked, for the notice and the booking window
);
$booker = new SessionBooker($reservations, $dispatcher, $clock);
```

### Le registre

`Contract\SeatLedger` est l'endroit où les réservations sont gardées :

- `bookings($session)` les liste, dans l'ordre où elles ont été faites ;
- `hold($session, $booking, $decide)` lit les réservations de la séance, interroge `$decide` sur la nouvelle avec
  elles, et la garde quand `$decide` donne null — le tout sous un seul verrou ou dans une seule transaction par séance,
  pour que ce qui est décidé soit ce qui est écrit.

`Store\InMemorySeatLedger` les garde en mémoire, pour les tests et les exemples ; `reset()` les oublie.
`tilttek/escape-room-bundle` fournit un registre sur Doctrine DBAL.

### Les règles d'une salle

`Reservation\Contract\RoomBookables::bookable($session, $capacity)` donne le `Bookable` de la salle d'une séance, avec
les règles de `phil/reservation` : ses heures d'ouverture, le préavis qu'elle demande, jusqu'où elle prend des
réservations à l'avance, les durées et les débuts de ses séances. Le `Bookable` détient la capacité qu'on lui donne — les
places de la salle —, sans quoi `InvalidRoom` est lancée. Sans `RoomBookables`, chaque salle est un `Bookable` avec sa
seule capacité :

```php
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Bookable;
use Phil\Reservation\Rule\MinimumNotice;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Reservation\Contract\RoomBookables;

final readonly class OpeningHours implements RoomBookables
{
    public function bookable(Session $session, int $capacity): Bookable
    {
        return new Bookable(
            capacity: $capacity,
            availability: new WeeklyAvailability([5 => [[17 * 60, 23 * 60]], 6 => [[10 * 60, 23 * 60]]], new DateTimeZone('America/Toronto')),
            rules: [new MinimumNotice(120)],
        );
    }
}
```

### Ce qui est refusé

Ce que le `Bookable` refuse — la première raison qu'il donne — est dit sous forme de `BookingRefusal`, que
`SessionBooker::book()` lance dans un `BookingRefused` :

| `ViolationCode` de `phil/reservation` | `BookingRefusal` |
|---|---|
| `Full` | `Taken` : une autre réservation a atteint la séance la première |
| `Quantity` | `TooFewPlayers` sous une place, `TooManyPlayers` au-dessus de la capacité |
| `Unavailable` | `Closed` : la salle n'est pas ouverte pendant toute la séance |
| `TooSoon` | `TooSoon` : la séance commence plus tôt que ne le permet le préavis |
| `TooFarAhead` | `TooFarAhead` : la séance commence plus loin que la salle ne prend de réservations |
| `TooShort`, `TooLong`, `OffGrid` | `OffSchedule` : la séance ne respecte pas les durées ou les débuts de la salle |

Les séances d'une salle sont celles de l'application : l'adaptateur vérifie chaque réservation d'après les
réservations de sa propre séance. Les rappels de `phil/reservation` rappellent aux groupes leur séance.
