# Bookings

## Sessions

A `Booking\Session` is a session of a room: its identifier in the application's reservations, the room, when it starts
and ends — kept in UTC, the end after the start — and how its seats are sold:

| `SessionMode` | Seats |
|---|---|
| `Private` | One party books the whole room |
| `Shared` | Parties book seats and play together, up to the room's largest team |
| `Either` | The first booking decides: a private booking takes the whole room, a booking of seats shares it |

## Booking

`Booking\SessionBooker::book()` books a session for a party — a member or a team, by a stable identifier, never an
email address nor a reference of the kind `erased`, which only an erasure writes — with its seats, private or not, under an identifier the application chooses:

```php
$booking = $booker->book($room, $session, ResourceReference::of('member', '42'), seats: 4, private: false, booking: 'b-01J8');
$booker->free($room, $session);   // the seats left: 2 in a room for 6
```

The room's rules are checked against the bookings already made; a refusal throws `BookingRefused`, whose `reason` is a
`Booking\BookingRefusal`:

| Reason | When |
|---|---|
| `OtherRoom` | The session is one of another room |
| `Started` | The session has started |
| `SharedOnly` | A private booking of a shared session |
| `PrivateOnly` | A booking of seats in a private session |
| `TooFewPlayers` | A private party smaller than the room's smallest team, or no seat |
| `TooManyPlayers` | A party larger than the room's largest team |
| `AlreadyBooked` | The party holds a booking of the session already |
| `Full` | The seats left, or a private booking, leave no room; a private booking of a session already booked |
| `Taken` | The reservations refused it: another booking reached the session first |
| `Closed` | The reservations refused it: the room is not open for the whole session |
| `TooSoon` | The reservations refused it: the session starts sooner than the room takes bookings |
| `TooFarAhead` | The reservations refused it: the session starts further ahead than the room takes bookings |
| `OffSchedule` | The reservations refused it: the session does not fit the lengths or the starts the room takes |

Once the rules pass, the reservations hold the booking and check again; `Event\SeatsBooked` is dispatched once they
hold it, never for a booking refused.

## The port to the reservations

`Contract\SessionReservations` is the application's reservations:

- `bookings($session)` lists the bookings of the session, in the order they were made;
- `reserve($session, $booking, $capacity)` holds the booking and gives null, or gives the `BookingRefusal` of the
  reservations: `Taken` when the seats were taken meanwhile, or one of their own rules. A private booking holds the
  whole capacity. Two parties booking the last seats at once do not both get them: the implementation checks again
  inside its own transaction or lock.

`Store\InMemorySessionReservations` keeps them in memory, for tests and examples; `reset()` forgets them.

## The reservations on phil/reservation

`Reservation\BookableSessionReservations` implements the port on `phil/reservation`, which the application installs:

```sh
composer require phil/reservation
```

A room is a `Bookable` whose capacity is its largest team, a session an `Interval` from its start to its end. A
private booking holds the whole capacity; a booking of seats holds as many units as its seats. Each booking is checked
by `Bookable::check()` against the bookings of the session, and kept only when the `Bookable` accepts it:

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

### The ledger

`Contract\SeatLedger` is where the bookings are kept:

- `bookings($session)` lists them, in the order they were made;
- `hold($session, $booking, $decide)` reads the bookings of the session, asks `$decide` about the new one with them,
  and keeps it when `$decide` gives null — all under one lock or inside one transaction per session, so that what is
  decided is what is written.

`Store\InMemorySeatLedger` keeps them in memory, for tests and examples; `reset()` forgets them.
`tilttek/escape-room-bundle` ships a ledger on Doctrine DBAL.

### The rules of a room

`Reservation\Contract\RoomBookables::bookable($session, $capacity)` gives the `Bookable` of the room of a session, with the rules
of `phil/reservation`: its opening hours, the notice it asks, how far ahead it takes bookings, the lengths and the starts
of its sessions. The `Bookable` holds the capacity it is given — the seats of the room —, or `InvalidRoom` is thrown.
Without `RoomBookables`, each room is a `Bookable` with its capacity alone:

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

### What is refused

What the `Bookable` refuses — the first reason it gives — is told as a `BookingRefusal`, which `SessionBooker::book()`
throws in a `BookingRefused`:

| `ViolationCode` of `phil/reservation` | `BookingRefusal` |
|---|---|
| `Full` | `Taken`: another booking reached the session first |
| `Quantity` | `TooFewPlayers` under one seat, `TooManyPlayers` over the capacity |
| `Unavailable` | `Closed`: the room is not open for the whole session |
| `TooSoon` | `TooSoon`: the session starts sooner than the notice allows |
| `TooFarAhead` | `TooFarAhead`: the session starts further ahead than the room takes bookings |
| `TooShort`, `TooLong`, `OffGrid` | `OffSchedule`: the session does not fit the lengths or the starts of the room |

The sessions of a room are the application's: the adapter checks each booking against the bookings of its own session.
The reminders of `phil/reservation` remind the parties of their session.
