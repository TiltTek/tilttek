<!-- phil-dev source-sha256=7df604205ea560ed373b9529553d4517ad9c8c2f0ef8015f8bdf57340c5675bb -->
# Gagner des points

## Règles de gain

Une `EarningRule` donne des points pour les activités qu'elle lit :

```php
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\EarningRule;
use Tilttek\Points\Rule\Period;

new EarningRule('games', ['game.played'], 10, cap: new Cap(50, Period::Week));
new EarningRule('orders', ['order.paid'], 1);                                    // one point per unit of quantity
new EarningRule('first-order', ['order.paid'], 100, cap: new Cap(100, Period::Ever), perQuantity: false);
new EarningRule('campaigns', ['game.*'], 2, 'stars', context: ['system' => 'dnd5e']);
```

- **Les verbes** sont des motifs de `tilttek/activity` : `game.played`, `game.*`, `*`. Une règle en lit au moins un.
- **Les points** vont de 1 à 1 000 000, multipliés par la quantité de l'activité, sauf si `perQuantity` est faux.
- **La devise** est celle par défaut quand aucune n'est nommée ; `PointsProjection` refuse une règle dans une devise
  qu'elle ne connaît pas.
- **Le plafond** limite ce que la règle donne à un membre sur une période : un jour, une semaine ISO à partir du lundi,
  un mois ou une année civile (comptés dans le fuseau horaire des `Rules`), ou pour toujours. Une activité qui
  dépasserait le plafond rapporte ce qui reste, et plus rien une fois le plafond atteint.
- **Les conditions de contexte** doivent toutes se trouver dans le contexte de l'activité, avec la même valeur et le
  même type.

`Rules` contient les règles d'une application, par nom unique, et le fuseau horaire de leurs périodes ;
`Rules::fromArray()` les construit à partir d'une configuration :

```php
Rules::fromArray([
    'games' => ['verbs' => ['game.played'], 'points' => 10, 'cap' => ['amount' => 50, 'period' => 'week']],
    'orders' => ['verbs' => ['order.paid'], 'points' => 1, 'currency' => 'stars', 'per_quantity' => true, 'context' => ['online' => true]],
], 'America/Toronto');
```

## La projection

`PointsProjection` est une projection de `tilttek/activity` : le `Projector` lui remet les entrées du journal que lisent
ses règles, et elle écrit les mouvements.

- **Une activité enregistrée** rapporte, pour chaque règle qui la récompense, un mouvement
  `#earn:<rule>:<activity key>` du compte d'émission de la règle vers le membre, daté au moment de l'activité. Une
  entrée remise deux fois ne rapporte qu'une fois.
- **Une activité annulée** renverse chaque gain qu'elle a causé par un mouvement `#reverse:<earning key>`, daté lui
  aussi au moment de l'activité, pour que le renversement libère le plafond de la période où l'activité a compté.

```php
$projector = new Projector($activityLog, $checkpoints);
$projector->catchUp($projection);            // by a scheduled task
new LiveProjections($projector, new Projections([$projection]));   // or as each entry is written
```

## Quand une règle change

Le journal d'activité n'est jamais réécrit ; les points qui en sont tirés sont recalculés à partir de lui :

```php
$projector->rebuild($projection, new DateTimeImmutable('2026-11-01'));
```

La projection oublie d'abord les mouvements dérivés (gagnés, renversés, expirés) dont le moment est à la date ou après
(tous, sans date), puis le journal de cette période est rejoué selon les nouvelles règles. Les mouvements décidés sont
gardés : une dépense faite avec des points qu'une règle ne donne plus reste, et le solde peut alors passer sous zéro,
comme après une annulation. L'expiration est recalculée par le prochain `expire()`.
