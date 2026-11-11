# Extension points

| Service or interface | What the application does with it |
|---|---|
| `Tilttek\Points\Points` (public) | Reads balances and histories, moves points |
| `Tilttek\Points\PointsProjection` (public, tagged `tilttek_activity.projection`) | Caught up and rebuilt by `tilttek/activity-bundle` |
| `Tilttek\Points\Ledger\Ledger` (public alias) | Replaces the ledger's storage: alias `Ledger` to the application's service |
| `Tilttek\Points\Currencies`, `Tilttek\Points\Rule\Rules` | Built from the configuration; replace their definitions to build them elsewhere |

The tag the projection carries is `PointsServices::PROJECTION_TAG`, the one `tilttek/activity-bundle` collects.
