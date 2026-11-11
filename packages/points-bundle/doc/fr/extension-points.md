<!-- phil-dev source-sha256=c5301edbde423c44c07d59e8dddd2e84182924db49057fc7bacfce7ba6e3f4e3 -->
# Points d'extension

| Service ou interface | Ce que l'application en fait |
|---|---|
| `Tilttek\Points\Points` (public) | Lit les soldes et les historiques, déplace des points |
| `Tilttek\Points\PointsProjection` (public, étiqueté `tilttek_activity.projection`) | Rattrapée et reconstruite par `tilttek/activity-bundle` |
| `Tilttek\Points\Ledger\Ledger` (alias public) | Remplace le stockage du registre : faites pointer l'alias `Ledger` vers le service de l'application |
| `Tilttek\Points\Currencies`, `Tilttek\Points\Rule\Rules` | Construits à partir de la configuration ; remplacez leurs définitions pour les construire ailleurs |

L'étiquette que porte la projection est `PointsServices::PROJECTION_TAG`, celle que recueille
`tilttek/activity-bundle`.
