# Bookings

The bundle wires `Tilttek\EscapeRoom\Contract\SessionReservations` and `Tilttek\EscapeRoom\Booking\SessionBooker`, which
books the sessions of the rooms as `tilttek/escape-room` describes: a private session for one party, or seats of a
shared session.

## The reservations

| The application | `SessionReservations` |
|---|---|
| names `reservations` | its own service |
| has installed `phil/reservation` | `Tilttek\EscapeRoom\Reservation\BookableSessionReservations`, over `DbalSeatLedger` — or `InMemorySeatLedger` with `store: memory` |
| neither | `Tilttek\EscapeRoom\Store\InMemorySessionReservations`, for tests only |

```sh
composer require phil/reservation
```

```php
$booking = $booker->book($room, $session, ResourceReference::of('team', 'owls'), seats: 4, private: true, booking: 'b-01J8');
```

The rules of each room — its opening hours, the notice it asks, how far ahead it takes bookings, the lengths and starts
of its sessions — are those of the `Bookable` that `room_bookables` gives:

```yaml
tilttek_escape_room:
    room_bookables: App\EscapeRoom\OpeningHours
```

A refusal of `phil/reservation` is told as a `BookingRefusal`: `Closed`, `TooSoon`, `TooFarAhead`, `OffSchedule`, or
`Taken` when another booking reached the session first.

## The lock of a session

`DbalSeatLedger` decides and writes a booking in one transaction that first writes the row of its session in
`tilttek_escape_room_session_lock`. Every database locks the row it updates until the transaction ends: a second
booking of the same session waits, then reads the first one. Two parties booking the last seats at once do not both get
them.

The identifiers of a session and a booking hold 255 bytes at most; a party is a reference of 320.
