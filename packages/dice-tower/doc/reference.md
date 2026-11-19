# Reference

| Type | Role |
|---|---|
| `TilttekDiceTowerBundle` | The bundle: its configuration and its services |
| `Domain\Model\TowerDrop` | A roll dropped in a table's tower: its position, the table, the roll, the roller and when |
| `Domain\Contract\TowerDropRepository` | The log of the towers |
| `Application\DiceTower` | Rolls dropped, entries read in order, missed entries read again, a roller's drops erased |
| `Application\TowerEntry` | An entry as everyone at the table sees it, and its payload |
| `Application\RollSummary` | The bounded summary of a recorded roll |
| `Application\TowerTopics` | The topic of each table's tower |
| `Application\TowerRelays` | Hands each entry to the relays, at once or through a bus |
| `Application\Message\RelayDrop`, `Application\RelayDropHandler` | The message of a relay, with the table and the position, and its handler |
| `Application\Contract\TableAccess` | Whether the current viewer may watch a table |
| `Application\Contract\TowerPublisher` | Pushes each new entry to the viewers |
| `Application\Contract\TowerRelay` | Carries each new entry to another channel |
| `Application\NoTableAccess`, `Application\NoTowerPublisher` | Nobody may watch; nothing is pushed |
| `Infrastructure\Doctrine\DoctrineTowerDropRepository` | The log in Doctrine |
| `Infrastructure\Memory\InMemoryTowerDropRepository` | The log in memory, for tests |
| `Infrastructure\Mercure\MercureTowerPublisher` | Pushes each entry to the private topic of its table |
| `Infrastructure\Mercure\TowerSubscriptions` | The subscription of an allowed viewer, and its authorization cookie |
| `Infrastructure\Http\TowerEntriesController` | The route of the missed entries |
| `Infrastructure\Twig\DiceTowerExtension` | `dice_tower()`, `dice_tower_topic()` and `dice_tower_kit()` |
| `Infrastructure\Twig\TowerView` | What the overlay shows of an entry |
| `Infrastructure\Twig\KitComponents` | The names of the components of the UX kit, and the recipes the overlay needs |
| `Infrastructure\DependencyInjection\TowerServices` | Registers the services |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the components of the kit after the Toolkit's subdirectory, unless `kit_subdirectory` says it |

| Service | Public |
|---|---|
| `Tilttek\DiceTower\Domain\Contract\TowerDropRepository` | yes, an alias of the store's log |
| `Tilttek\DiceTower\Application\DiceTower` | yes |
| `Tilttek\DiceTower\Application\TowerTopics` | yes |
| `Tilttek\DiceTower\Application\Contract\TableAccess` | no, an alias of `table_access` or of `NoTableAccess` |
| `Tilttek\DiceTower\Application\Contract\TowerPublisher` | no, an alias of `MercureTowerPublisher` |
| `Tilttek\DiceTower\Application\TowerRelays` | no |
| `Tilttek\DiceTower\Application\RelayDropHandler` | no, with `message_bus` |
| `Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions` | yes |
| `Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController` | yes |
| `Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension` | yes, with Twig |

| Route | Path |
|---|---|
| `tilttek_dice_tower_entries` | `GET /dice-tower/{table}/entries?after={position}` |

| Assets | What they are |
|---|---|
| `assets/src/tower_controller.js` | The controller `tilttek--dice-tower--tower` |
| `assets/src/lib/entries.js` | The order, the words and the address of the entries |
