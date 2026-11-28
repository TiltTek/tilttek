# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `definition_store` | A service of `Tilttek\Sets\Contract\DefinitionStore`: the versions of the sets | [Configuration](configuration.md) |
| `completion_store` | A service of `Tilttek\Sets\Contract\CompletionStore` that also implements `PendingDeliveries`: the completions, and the holders waiting with the failures of their delivery | [Configuration](configuration.md) |
| `clock` | A PSR-20 clock | [Configuration](configuration.md) |
| Event listeners | Listeners of `SetCompleted` and `TierReached`, which grant the rewards in idempotent handlers, and of `DeliveryKeepsFailing`, which log or alert someone | [Resuming deliveries](resuming-deliveries.md) |
| The schedule's lock | A `Symfony\Component\Lock\LockInterface` service | [Resuming deliveries](resuming-deliveries.md#the-schedule) |
