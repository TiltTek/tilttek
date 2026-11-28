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

| Key | Default | What it sets |
|---|---|---|
| `definition_store` | null | The service id of the store of the definitions; `InMemoryDefinitionStore` when null. |
| `completion_store` | null | The service id of the store of the completions; `InMemoryCompletionStore` when null. |
| `clock` | null | The service id of the PSR-20 clock the completions are dated by; the library's clock in UTC when null. |
| `backoff.first_delay` | `60` | The delay, in seconds, before a holder whose delivery failed is told again; doubled after each failure that follows. |
| `backoff.longest_delay` | `86400` | The longest delay, in seconds, not under the first. |
| `backoff.report_after` | `3` | The failures in a row from which each failure is told by a `DeliveryKeepsFailing` event. |
| `schedule.enabled` | `true` | Whether the `tilttek_sets` schedule is registered, when `symfony/scheduler` is installed. |
| `schedule.frequency` | `5 minutes` | The interval between two runs: a relative format, or seconds. |
| `schedule.limit` | `100` | The most holders a run tells, 1 to 10 000. |
| `schedule.lock` | null | The service id of a lock shared by the workers that consume the schedule. |

`Sets` hears the `event_dispatcher` service, when the application has one: the events of the completions, and those
of the repeated failures, reach its listeners.

A longest delay under the first, or a limit out of bounds, is refused as the container is built.
