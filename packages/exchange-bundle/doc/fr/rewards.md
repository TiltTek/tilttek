<!-- phil-dev source-sha256=e3d8c4b117c461acb379287157842a700763533fae2830c6fef58fd6f2c0edd7 -->
# Récompenses

## Le catalogue

Avec `catalog: configuration`, les récompenses sont celles de `rewards`. Avec `catalog: doctrine`, elles vivent dans la
table `tilttek_exchange_reward`, que l'application modifie par l'alias `Catalog`, un `WritableCatalog` :

```php
use Tilttek\Exchange\Reward\Catalog;
use Tilttek\Exchange\Reward\Reward;

$catalog->save(new Reward('seat', 250, stock: 30, perMember: 1));
$catalog->remove('discount-10');
```

## Réserver

`Redemptions` est un service public. Un contrôleur ou un *handler* réserve avec une clé construite à partir de ce qui
rend l'échange unique (une commande, un membre et une récompense), pour qu'une requête relancée ne réserve qu'une fois :

```php
$reservation = $redemptions->reserve($member, 'seat', 'order:'.$order->id.':seat');
```

Les raisons pour lesquelles un membre ne peut pas réserver (`refusal()`) et les unités restantes (`remaining()`)
alimentent une page de récompenses.

## Livrer

Un écouteur de `Tilttek\Exchange\Event\RewardReserved` livre, puis confirme ; ou il annule quand il ne peut pas livrer,
et les points reviennent :

```php
#[AsEventListener]
final readonly class DeliverSeat
{
    public function __construct(private Redemptions $redemptions, private Tickets $tickets) {}

    public function __invoke(RewardReserved $event): void
    {
        if ('seat' !== $event->reservation->reward) {
            return;
        }

        $this->tickets->issue($event->reservation->member, $event->reservation->key->value);
        $this->redemptions->confirm($event->reservation->key->value);
    }
}
```

`tilttek:exchange:expire`, lancée par un planificateur, fait expirer les réservations qui ont dépassé leur durée de vie.

## Les points qu'une réservation retient

`Tilttek\ExchangeBundle\Application\Contract\PointsHold` nomme les trois gestes qu'une récompense réservée fait sur
les points du membre, chacun identifié par la réservation et sans danger s'il est exécuté deux fois :

| Méthode | Quand | Effet |
|---|---|---|
| `hold(Reservation)` | La récompense est réservée | Le montant quitte le solde disponible ; `InsufficientPoints` quand le solde ne le couvre pas, et rien n'est retenu. Retourne si cet appel l'a retenu. |
| `confirm(Reservation)` | La récompense est livrée | Les points retenus restent dépensés. |
| `release(Reservation)` | La réservation est annulée, refusée ou expire | Les points retenus reviennent au membre. |
| `held(member, currency)` | — | Les points retenus, ni confirmés ni rendus. |
| `available(member, currency)` | — | Le solde moins les points retenus : ce qu'une nouvelle réservation peut dépenser. |

Une réservation est réglée une seule fois, confirmée ou rendue et jamais les deux, comme le garantit le `transition()`
du magasin.

Le bundle enregistre `SpendingPointsHold` sous l'alias `PointsHold`. Il retient en dépensant les points aussitôt et les
rend par un remboursement, par le service `Points` : la dépense est écrite sous la clé de dépense de la réservation,
`exchange:reservation:` suivi de sa clé, sur le compte `reward:` suivi du code de la récompense, soit les mouvements
mêmes qu'écrit `Redemptions`. Une retenue et une réservation de même clé forment donc une seule dépense, et l'un ou
l'autre peut la rendre. Comme les points quittent le solde dès qu'ils sont retenus, `held()` vaut toujours 0 et
`available()` est le solde.

Une application qui retient des points hors de `Redemptions` (une récompense accordée à la main, une réservation prise
par un autre canal) passe par l'alias. Ainsi, une retenue gardée comme une écriture en attente du registre remplace
`SpendingPointsHold` sans changer son code :

```php
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;

$hold->hold($reservation);
// … the application delivers …
$hold->confirm($reservation);
```
