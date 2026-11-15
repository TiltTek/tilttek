<!-- phil-dev source-sha256=b809e9243caa3a0b34e6f6276d3d26c0a54524d9239ab71ada96f25deee30e83 -->
# Séquestre

Une prime est payée d'avance : ses points quittent le solde du commanditaire quand elle est offerte, et sont détenus
jusqu'au règlement de la prime — payés aux gagnants, le reste rendu. La bibliothèque les déplace par
`Contract\BountyEscrow`.

## Les trois mouvements

| Mouvement | Quand | Ce qu'il fait |
|---|---|---|
| `reserve($hold)` | La prime est offerte | Retire le montant des points du commanditaire ; `InsufficientPoints` quand ils ne le couvrent pas |
| `confirm($hold, $payouts)` | La prime est attribuée | Paie chaque paiement, et rend au commanditaire ce que les paiements laissent |
| `cancel($hold)` | La prime est annulée, ou expire sans rien d'attribué | Rend tout au commanditaire |

Chaque mouvement reçoit la `BountyHold` entière : sa clé, `bounty:` suivi de l'identifiant de la prime ; le payeur ; le
montant et son unité de points ; depuis quand elle est détenue et jusqu'à quand, la fin du délai de grâce. Chaque
mouvement est indexé par la détention, donc le même mouvement fait deux fois a l'effet d'un seul : une requête
retentée, ou `settle()` après un séquestre qui a échoué, ne déplace aucun point deux fois. Confirmer une détention qui
n'a jamais été détenue la détient d'abord, pour qu'aucun point ne soit payé sans que le payeur l'ait donné ; annuler une
détention qui n'a jamais été détenue ne fait rien. La bibliothèque règle une détention une seule fois, confirmée ou
annulée, comme s'en assure la machine à états de la prime.

Les mouvements suivent ceux du contrat `PointsHold` de `tilttek/exchange-bundle` — détenir, confirmer, libérer —, qui
reçoivent la réservation entière comme ceux-ci reçoivent la détention entière.

## Les séquestres fournis

- `Escrow\PointsBountyEscrow` détient les points dans le registre de `tilttek/points` : une détention est une dépense
  des points du commanditaire sous la clé de la détention, sur le compte de rachat `bounties` ; une confirmation accorde
  chaque paiement depuis le compte d'émission `bounties`, sous `<key>:payout:` et une empreinte du membre, et rend ce
  que les paiements laissent sous `<key>:remainder` ; une annulation rembourse la dépense.
- `Escrow\InMemoryBountyEscrow` conserve les points et les détentions en mémoire, pour les tests : `fund()` donne des
  points à un membre, `balance()` et `held()` les lisent, et il refuse avec `EscrowConflict` ce qui contredit une
  détention — une autre détention sous la même clé, des paiements supérieurs au montant, une détention réglée dans un
  sens puis dans l'autre.

## Un séquestre sur `tilttek/exchange-bundle`

Une application qui détient ses récompenses par le contrat `PointsHold` de `tilttek/exchange-bundle` détient ses primes
de la même façon, avec un adaptateur à elle :

```php
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\Points\Points;

final readonly class ExchangeBountyEscrow implements BountyEscrow
{
    public function __construct(
        private PointsHold $holds,
        private Points $points,
    ) {
    }

    public function reserve(BountyHold $hold): bool
    {
        return $this->holds->hold($this->reservation($hold));
    }

    public function confirm(BountyHold $hold, array $payouts): void
    {
        $this->holds->hold($this->reservation($hold));      // a hold never held is held first
        $this->holds->confirm($this->reservation($hold));
        $paid = 0;

        foreach ($payouts as $payout) {
            $this->points->grant($payout->member, $payout->amount, $hold->key.':payout:'.hash('xxh128', $payout->member->toString()), 'bounties', $hold->unit);
            $paid += $payout->amount;
        }

        if ($paid < $hold->amount) {
            $this->points->grant($hold->payer, $hold->amount - $paid, $hold->key.':remainder', 'bounties', $hold->unit);
        }
    }

    public function cancel(BountyHold $hold): void
    {
        $this->holds->release($this->reservation($hold));
    }

    private function reservation(BountyHold $hold): Reservation
    {
        return new Reservation(ExchangeKey::fromString($hold->key), $hold->payer, 'bounty', 1, $hold->amount, $hold->unit, $hold->heldAt, $hold->until);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Points\ExchangeBountyEscrow: ~
    Tilttek\Bounties\Contract\BountyEscrow: '@App\Points\ExchangeBountyEscrow'
```

Son `available()` laisse alors de côté les primes détenues, comme il laisse de côté les récompenses réservées. Quand le
registre de `tilttek/points` détient lui-même des points en attente, l'implémentation derrière `PointsHold` change, et
cet adaptateur reste tel quel.

## Jamais de l'argent

Une prime est faite de points du site, dans l'une de ses unités de `tilttek/points` — `points`, `gems` — : une unité
d'argent n'est pas une unité de points, et rien dans la bibliothèque n'achète, ne vend ni ne convertit des points. La
bibliothèque n'a ni champ ni adaptateur pour l'argent, et il ne faut pas en ajouter : une prime conservée prépayée et
rendue automatiquement quand personne ne la mérite est un jeu, pas un paiement.
