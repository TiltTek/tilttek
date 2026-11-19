<!-- phil-dev source-sha256=4544f005ec9a34218a3a1b9836fba7180a6ee3f0fb818ae500cce53125f5621c -->
# Événements

Les événements des échanges (`RewardReserved`, `ReservationConfirmed`, `ReservationCancelled`, `ReservationExpired`,
`PointsTransferred`, `OfferMade`, `OfferAccepted`, `OfferDeclined`, `OfferWithdrawn`, `OfferCountered`,
`OfferExpired`, tous sous `Tilttek\Exchange\Event`) sont transmis au répartiteur d'événements de l'application par
`DispatchingAnnouncer`, le service derrière l'alias `Tilttek\Exchange\Announcer`. Chacun est transmis une seule fois,
quand il se produit ; un échange demandé de nouveau ne transmet rien.

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

Un écouteur s'exécute dans l'appel qui a fait l'échange : un écouteur qui lève une exception fait échouer l'appel après
que l'échange est écrit.
