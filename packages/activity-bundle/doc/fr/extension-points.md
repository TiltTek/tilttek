<!-- phil-dev source-sha256=1800523462f9ad5111535ffe4720e1e44753d78f124b4b33465f56e9d55044bb -->
# Points d'extension

| Interface | Étiquette, par configuration automatique | Ce qu'en fait l'application |
|---|---|---|
| `Tilttek\Activity\Projection\Projection` | `tilttek_activity.projection` | Tient un état dérivé du journal, que le bundle fait rattraper et reconstruit |
| `Tilttek\Activity\JournalObserver` | `tilttek_activity.observer` | Est averti de chaque nouvelle entrée, dans le même processus |
| `Tilttek\Activity\Log\ActivityLog` | — | Remplace le stockage du journal : faites de `ActivityLog` un alias du service de l'application |
| `Tilttek\Activity\Projection\Checkpoints` | — | Remplace le stockage des points de reprise : faites de `Checkpoints` un alias du service de l'application |

Les services `Journal`, `Projector`, `Projections`, `ActivityLog` et `Checkpoints` sont publics.
