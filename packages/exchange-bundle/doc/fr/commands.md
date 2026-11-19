<!-- phil-dev source-sha256=256c13c1f4f13aac7e23251be3c5f897ff083cfd5450dd9509763d60a48151cc -->
# Commandes

Quand `symfony/console` est installé :

| Commande | Ce qu'elle fait |
|---|---|
| `tilttek:exchange:expire` | Fait expirer les réservations qui ont dépassé leur durée de vie, en rendant leurs points, et, quand les échanges sont activés, les offres qui ont dépassé la leur, en libérant leurs objets. Affiche `2 reservations and 1 offers expired.` Un planificateur la lance. |
| `tilttek:exchange:transfers [member] [--limit=50]` | Montre le journal des transferts : les plus récents de tous les membres, du plus récent au plus ancien, au plus 1000, ou chaque transfert donné ou reçu par un membre, comme `member:44`. Existe quand les transferts sont activés. |
