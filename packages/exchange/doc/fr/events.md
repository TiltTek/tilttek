<!-- phil-dev source-sha256=63993e7cc6104441c2721596f99b130efea99144d0057fb80ae448bd06f727e6 -->
# Événements

`Redemptions`, `Transfers` et `Trades` informent leur `Announcer` de ce qui s'est passé, une seule fois, au moment où
cela se produit ; un échange demandé de nouveau n'annonce rien. `SilentAnnouncer` n'informe personne ; le bundle
transmet les événements au répartiteur d'événements de l'application. Chaque événement implémente
`Event\ExchangeEvent`.

| Événement | Quand | Porte |
|---|---|---|
| `RewardReserved` | Les points d'une réservation sont dépensés : livrez maintenant | `reservation` |
| `ReservationConfirmed` | L'application a confirmé la livraison | `reservation` |
| `ReservationCancelled` | L'application a annulé ; les points sont revenus | `reservation` |
| `ReservationExpired` | La réservation a dépassé sa durée de vie ; les points sont revenus | `reservation` |
| `PointsTransferred` | Un membre a donné des points à un autre | `transfer` |
| `OfferMade` | Un membre a proposé un échange | `offer` |
| `OfferAccepted` | Les deux côtés ont changé de mains | `offer` |
| `OfferDeclined` | Le destinataire a refusé | `offer` |
| `OfferWithdrawn` | L'auteur de l'offre l'a retirée | `offer` |
| `OfferCountered` | Le destinataire a répondu par sa propre offre | `offer`, fermée, et `counter`, ouverte |
| `OfferExpired` | L'offre a dépassé sa durée de vie | `offer` |

Livrer une récompense est l'affaire de l'application : un écouteur de `RewardReserved` envoie le code, applique le
rabais ou réserve la place, puis confirme la réservation, ou l'annule s'il ne le peut pas.
