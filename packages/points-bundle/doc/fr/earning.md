<!-- phil-dev source-sha256=17413ccec5a48e69631db20871c856c19aa4642c21fe8200fe40ce73d08fe70e -->
# Gagner grâce à l'activité

Le bundle enregistre `PointsProjection`, étiquetée `tilttek_activity.projection` : `tilttek/activity-bundle` la trouve
avec ses autres projections. Avec ses `live_projections` (par défaut), chaque activité enregistrée par le `Journal`
rapporte ses points dans la même requête :

```php
$journal->record(Activity::of('game:812:member:44', 'member:44', 'game.played', $playedAt, 'game:812'));
$points->balance(ResourceReference::of('member', '44')); // 10
$journal->cancel('game:812:member:44');                  // the points are reversed
```

Sans projections en direct, une tâche planifiée la rattrape :

```sh
bin/console tilttek:activity:catch-up points
```

## Quand une règle change

Changez la règle dans la configuration, déployez, puis reconstruisez la période à laquelle elle s'applique :

```sh
bin/console tilttek:activity:rebuild points --since=2026-11-01
```

Les mouvements dérivés (gagnés, renversés, expirés) à partir de cette date sont oubliés, et le journal de la période est
rejoué selon les nouvelles règles ; les dépenses, les transferts, les attributions et les remboursements restent.
Lancez ensuite `tilttek:points:expire` quand une devise expire.

Les verbes que lisent les règles doivent faire partie des verbes que déclare `tilttek_activity`, s'il en déclare.
