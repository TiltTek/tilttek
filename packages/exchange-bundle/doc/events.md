# Events

The exchanges' events — `RewardReserved`, `ReservationConfirmed`, `ReservationCancelled`, `ReservationExpired`,
`PointsTransferred`, `OfferMade`, `OfferAccepted`, `OfferDeclined`, `OfferWithdrawn`, `OfferCountered`,
`OfferExpired`, all under `Tilttek\Exchange\Event` — are dispatched to the application's event dispatcher by
`DispatchingAnnouncer`, the service behind the `Tilttek\Exchange\Announcer` alias. Each is dispatched once, when it
happens; an exchange asked again dispatches nothing.

```php
#[AsEventListener]
final readonly class TellTheRecipient
{
    public function __invoke(OfferMade $event): void
    {
        // notify $event->offer->recipient
    }
}
```

A listener runs inside the call that made the exchange: a listener that throws makes the call throw after the exchange
is written.
