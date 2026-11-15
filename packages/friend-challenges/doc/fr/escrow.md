<!-- phil-dev source-sha256=adbd62afe0f204c7f3fde8bd01b25dc55dd5c137e89f95bc930acf4344dd173b -->
# Séquestre

Le lot d'un défi est fait de points que le créateur cède à la création du défi, détenus jusqu'au règlement du défi :
payés au gagnant, ou rendus au créateur. La bibliothèque les déplace par `Contract\PrizeEscrow`.

## Les trois mouvements

| Mouvement | Quand | Ce qu'il fait |
|---|---|---|
| `reserve($hold)` | Le défi est créé | Retire le lot des points du créateur ; `InsufficientPoints` quand ils ne le couvrent pas |
| `confirm($hold, $payouts)` | Le défi est gagné | Paie chaque paiement, et rend au créateur ce que les paiements laissent |
| `cancel($hold)` | Le défi est annulé, expire ou est retiré | Rend le lot au créateur |

Chaque mouvement reçoit la `PrizeHold` entière : sa clé, `friend-challenge:` suivi de l'identifiant du défi ; le payeur ;
le montant et son unité de points ; depuis quand elle est détenue et jusqu'à quand, la fin de l'examen. Chaque
mouvement est indexé par la détention, donc le même mouvement fait deux fois a l'effet d'un seul : une requête
retentée, ou `settle()` après un séquestre qui a échoué, ne déplace aucun point deux fois. Confirmer une détention qui
n'a jamais été détenue la détient d'abord, pour qu'aucun point ne soit payé sans que le payeur l'ait donné ; annuler une
détention qui n'a jamais été détenue ne fait rien. La bibliothèque règle une détention une seule fois, confirmée ou
annulée, comme s'en assure la machine à états du défi.

Les mouvements suivent ceux du contrat `PointsHold` de `tilttek/exchange-bundle` — détenir, confirmer, libérer —, qui
reçoivent la réservation entière comme ceux-ci reçoivent la détention entière.

## Les séquestres fournis

- `Escrow\PointsPrizeEscrow` détient le lot dans le registre de `tilttek/points` : une détention est une dépense des
  points du créateur sous la clé de la détention, sur le compte de rachat `friend-challenges` ; une confirmation
  accorde chaque paiement depuis le compte d'émission `friend-challenges`, sous `<key>:payout:` et une empreinte du
  membre, et rend ce que les paiements laissent sous `<key>:remainder` ; une annulation rembourse la dépense. Les points
  quittent le solde du créateur quand ils sont détenus.
- `Escrow\InMemoryPrizeEscrow` conserve les points et les détentions en mémoire, pour les tests : `fund()` donne des
  points à un membre, `balance()` et `held()` les lisent, et il refuse avec `EscrowConflict` ce qui contredit une
  détention — une autre détention sous la même clé, des paiements supérieurs au montant, une détention réglée dans un
  sens puis dans l'autre.

## Un séquestre sur `tilttek/exchange-bundle`

Une application qui détient ses récompenses par le contrat `PointsHold` de `tilttek/exchange-bundle` détient ses lots de
la même façon, avec un adaptateur à elle :

```php
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\Points\Points;

final readonly class ExchangePrizeEscrow implements PrizeEscrow
{
    public function __construct(
        private PointsHold $holds,
        private Points $points,
    ) {
    }

    public function reserve(PrizeHold $hold): bool
    {
        return $this->holds->hold($this->reservation($hold));
    }

    public function confirm(PrizeHold $hold, array $payouts): void
    {
        $this->holds->hold($this->reservation($hold));      // a hold never held is held first
        $this->holds->confirm($this->reservation($hold));
        $paid = 0;

        foreach ($payouts as $payout) {
            $this->points->grant($payout->member, $payout->amount, $hold->key.':payout:'.hash('xxh128', $payout->member->toString()), 'friend-challenges', $hold->unit);
            $paid += $payout->amount;
        }

        if ($paid < $hold->amount) {
            $this->points->grant($hold->payer, $hold->amount - $paid, $hold->key.':remainder', 'friend-challenges', $hold->unit);
        }
    }

    public function cancel(PrizeHold $hold): void
    {
        $this->holds->release($this->reservation($hold));
    }

    private function reservation(PrizeHold $hold): Reservation
    {
        return new Reservation(ExchangeKey::fromString($hold->key), $hold->payer, 'friend-challenge', 1, $hold->amount, $hold->unit, $hold->heldAt, $hold->until);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Points\ExchangePrizeEscrow: ~
    Tilttek\FriendChallenges\Contract\PrizeEscrow: '@App\Points\ExchangePrizeEscrow'
```

Son `available()` laisse alors de côté les lots détenus, comme il laisse de côté les récompenses réservées. Quand le
registre de `tilttek/points` détient lui-même des points en attente, l'implémentation derrière `PointsHold` change, et
cet adaptateur reste tel quel.

## Jamais de l'argent

Le lot est fait de points du site, dans l'une de ses unités de `tilttek/points` — `points`, `gems` — : une unité
d'argent n'est pas une unité de points, et rien dans la bibliothèque n'achète, ne vend ni ne convertit des points. La
bibliothèque n'a ni champ ni adaptateur pour l'argent.
