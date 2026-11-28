# Reference

| Class | Role |
|---|---|
| `TilttekSetsBundle` | The bundle and its configuration, under `tilttek_sets` |
| `Infrastructure\DependencyInjection\SetsServices` | Registers the stores in memory, the backoff, `Sets`, and the command, the handler and the schedule whose components are installed |
| `Infrastructure\Console\DeliverCommand` | The `tilttek:sets:deliver` command: `Sets::deliverPending()`, or `Sets::deliver()` with `--holder` |
| `Infrastructure\Messenger\DeliverPendingCompletions` | The message that asks for a pending delivery; it carries the limit only, 100 by default |
| `Infrastructure\Messenger\DeliverPendingCompletionsHandler` | Its handler, which calls `Sets::deliverPending()` |
| `Infrastructure\Scheduler\DeliverySchedule` | The `tilttek_sets` schedule: the message at an interval, five minutes by default, with an optional lock |

| Service | What it is |
|---|---|
| `Tilttek\Sets\Sets` | Public: the sets of the application |
| `Tilttek\Sets\DeliveryBackoff` | The backoff of the configuration |
| `Tilttek\Sets\Store\InMemoryDefinitionStore`, `Tilttek\Sets\Store\InMemoryCompletionStore` | The stores, when the configuration names none |
