<!-- phil-dev source-sha256=81c1f796e7bc279f5c0d55df922d4eeb5902d0ef7f62e2613ee905efecd92400 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `definition_store` | Un service de `Tilttek\Sets\Contract\DefinitionStore` : les versions des ensembles | [Configuration](configuration.md) |
| `completion_store` | Un service de `Tilttek\Sets\Contract\CompletionStore` qui implémente aussi `PendingDeliveries` : les complétions, et les détenteurs qui attendent avec les échecs de leur remise | [Configuration](configuration.md) |
| `clock` | Une horloge PSR-20 | [Configuration](configuration.md) |
| Écouteurs d'événements | Des écouteurs de `SetCompleted` et de `TierReached`, qui accordent les récompenses dans des gestionnaires idempotents, et de `DeliveryKeepsFailing`, qui journalisent ou préviennent quelqu'un | [Reprendre les remises](resuming-deliveries.md) |
| Le verrou du planificateur | Un service de `Symfony\Component\Lock\LockInterface` | [Reprendre les remises](resuming-deliveries.md#le-planificateur) |
