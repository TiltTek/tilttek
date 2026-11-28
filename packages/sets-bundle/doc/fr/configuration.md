<!-- phil-dev source-sha256=aec3030383846f5a0af828861e7ea5a54e0d40e26bcd8827c8e92058515f404f -->
# Configuration

```yaml
# config/packages/tilttek_sets.yaml
tilttek_sets:
    definition_store: App\Sets\DefinitionStore   # a Tilttek\Sets\Contract\DefinitionStore
    completion_store: App\Sets\CompletionStore   # a CompletionStore that also implements PendingDeliveries
    clock: clock
    backoff:
        first_delay: 60        # seconds after a first failure
        longest_delay: 86400   # never longer
        report_after: 3        # DeliveryKeepsFailing from the third failure in a row
    schedule:
        frequency: 5 minutes
        limit: 100
        lock: app.sets_delivery_lock
```

| Clé | Par défaut | Ce qu'elle règle |
|---|---|---|
| `definition_store` | null | L'identifiant du service du stockage des définitions ; `InMemoryDefinitionStore` quand il est nul. |
| `completion_store` | null | L'identifiant du service du stockage des complétions ; `InMemoryCompletionStore` quand il est nul. |
| `clock` | null | L'identifiant du service de l'horloge PSR-20 qui date les complétions ; l'horloge en UTC de la bibliothèque quand il est nul. |
| `backoff.first_delay` | `60` | Le délai, en secondes, avant d'informer de nouveau un détenteur dont la remise a échoué ; doublé après chaque échec qui suit. |
| `backoff.longest_delay` | `86400` | Le plus long délai, en secondes, pas sous le premier. |
| `backoff.report_after` | `3` | Les échecs de suite à partir desquels chaque échec est annoncé par un événement `DeliveryKeepsFailing`. |
| `schedule.enabled` | `true` | Si le planificateur `tilttek_sets` est enregistré, quand `symfony/scheduler` est installé. |
| `schedule.frequency` | `5 minutes` | L'intervalle entre deux exécutions : un format relatif, ou des secondes. |
| `schedule.limit` | `100` | Le plus de détenteurs qu'une exécution informe, de 1 à 10 000. |
| `schedule.lock` | null | L'identifiant du service d'un verrou partagé par les *workers* qui consomment le planificateur. |

`Sets` écoute le service `event_dispatcher`, quand l'application en a un : les événements des complétions, et ceux des
échecs répétés, parviennent à ses écouteurs.

Un plus long délai sous le premier, ou une limite hors bornes, est refusé à la construction du conteneur.
