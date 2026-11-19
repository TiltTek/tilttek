<!-- phil-dev source-sha256=52f32a6a68ff1c7aeeadd096a02deb7a63cc0d6105cc22eb01f868acf256922b -->
# Réservations

Le bundle branche `Tilttek\EscapeRoom\Contract\SessionReservations` et `Tilttek\EscapeRoom\Booking\SessionBooker`, qui
réserve les séances des salles comme le décrit `tilttek/escape-room` : une séance privée pour un groupe, ou des places
d'une séance partagée.

## Les réservations

| L'application | `SessionReservations` |
|---|---|
| nomme `reservations` | son propre service |
| a installé `phil/reservation` | `Tilttek\EscapeRoom\Reservation\BookableSessionReservations`, sur `DbalSeatLedger` — ou `InMemorySeatLedger` avec `store: memory` |
| ni l'un ni l'autre | `Tilttek\EscapeRoom\Store\InMemorySessionReservations`, pour les tests seulement |

```sh
composer require phil/reservation
```

```php
$booking = $booker->book($room, $session, ResourceReference::of('team', 'owls'), seats: 4, private: true, booking: 'b-01J8');
```

Les règles de chaque salle — ses heures d'ouverture, le préavis qu'elle demande, jusqu'où elle prend des réservations à
l'avance, les durées et les débuts de ses séances — sont celles du `Bookable` que donne `room_bookables` :

```yaml
tilttek_escape_room:
    room_bookables: App\EscapeRoom\OpeningHours
```

Un refus de `phil/reservation` est dit sous forme de `BookingRefusal` : `Closed`, `TooSoon`, `TooFarAhead`,
`OffSchedule`, ou `Taken` quand une autre réservation a atteint la séance la première.

## Le verrou d'une séance

`DbalSeatLedger` décide et écrit une réservation dans une seule transaction qui écrit d'abord la ligne de sa séance dans
`tilttek_escape_room_session_lock`. Toute base de données verrouille la ligne qu'elle met à jour jusqu'à la fin de la
transaction : une deuxième réservation de la même séance attend, puis lit la première. Deux groupes qui réservent en
même temps les dernières places ne les obtiennent pas tous les deux.

Les identifiants d'une séance et d'une réservation contiennent 255 octets au plus ; un groupe est une référence de 320.
