<!-- phil-dev source-sha256=a741a599b8847c43cb0bf632dc8777c06e63495827dfb1e141cb7e78a559cb73 -->
# Points d'extension

| Service ou alias | Public | Ce que c'est |
|---|---|---|
| `Tilttek\Exchange\Reservation\Redemptions` | Oui | Réserver, confirmer, annuler, faire expirer |
| `Tilttek\Exchange\Transfer\Transfers` | Oui, quand activé | Donner des points ; le journal |
| `Tilttek\Exchange\Trade\Trades` | Oui, quand activé | Offrir, accepter, refuser, retirer, contre-offrir, faire expirer |
| `Tilttek\Exchange\ExchangeErasure` | Oui | Effacer les échanges d'un membre |
| `Tilttek\Exchange\Reward\Catalog` | Oui | Le catalogue, un `WritableCatalog` avec `catalog: doctrine` |
| `Tilttek\Exchange\Reservation\ReservationStore`, `…\Transfer\TransferLog`, `…\Trade\OfferStore`, `…\Transaction` | Oui | Les magasins et la transaction |
| `Tilttek\ExchangeBundle\Application\Contract\PointsHold` | Oui | Les points d'une récompense réservée retenus, gardés ou rendus ; `SpendingPointsHold` par défaut |
| `Tilttek\Exchange\Announcer` | Non | `DispatchingAnnouncer`, vers le répartiteur d'événements |

L'application fournit, par identifiant de service dans la configuration : les `Requirements` des conditions des
récompenses, le `MemberSince` des dates d'inscription et l'`Inventory` des objets. Elle peut remplacer un alias par son
propre magasin, ou `PointsHold` par sa propre retenue de points.

Le bundle utilise les services `Points` et `Currencies` de `tilttek/points-bundle`, et le service `clock`.
