<!-- phil-dev source-sha256=d1d0e3e77543b8e29b5eeb8bd1f2786e355f5406ae456f30d2345638d5289bd3 -->
# Transferts

Quand l'application le permet, les membres se donnent des points. `Transfers::transfer($from, $to, $amount, $key,
$currency)` vérifie les gardes ([Gardes](guards.md)), déplace les points par un transfert du grand livre sous
`exchange:transfer:` et la clé, ce qui ne rend jamais négatif le solde de celui qui donne, puis écrit le transfert dans
le journal.

```php
$transfers->transfer($member, $friend, 50, 'gift:2026-11-07:44');
```

La même clé ne donne qu'une fois : redemandé, le transfert déjà écrit est retourné, sans égard aux gardes ni à
l'horloge ; la même clé pour un autre transfert est un `ExchangeConflict`.

## Le journal

Le `TransferLog` garde chaque transfert. `journal($limit)` donne les plus récents de tous les membres, le plus récent
d'abord, 50 à moins d'en demander autrement et 1000 au plus : ce qu'examine un modérateur. `ofMember($member)` donne
ceux qu'un membre a donnés ou reçus, le plus ancien d'abord. `given($member, $currency)` donne ce qu'un membre a donné
sur la période en cours de l'allocation, ou depuis toujours sans allocation.
