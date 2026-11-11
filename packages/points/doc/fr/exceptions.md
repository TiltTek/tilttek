<!-- phil-dev source-sha256=7b8b9b049f590702e813cb3514a008921171e83b843a2a62825fc4b47156d256 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Points\Exception\PointsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidPoints` | Un compte, une devise, une durée de vie, une clé, un montant, une règle, un plafond, un fuseau horaire, une lecture ou une taille de lot est refusé | `"Stars" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.` |
| `MovementConflict` | Une clé déjà écrite est écrite de nouveau pour un autre mouvement | `The movement key "gift:1" is already written for another movement.` |
| `InsufficientPoints` | Une dépense ou un transfert dépasse le solde du payeur | `The account "member:member:44" holds 30 points; 31 cannot be paid from it.` |
| `UnknownCurrency` | Une devise est demandée par un code qu'aucune ne porte | `No currency of points is named "crowns".` |
| `UnknownMovement` | Un remboursement ne nomme aucune dépense | `No spending is written under the key "order:2".` |

Les motifs de verbes et les conditions de contexte d'une règle sont vérifiés par `tilttek/activity`, qui lève sa propre
`InvalidActivity`.
