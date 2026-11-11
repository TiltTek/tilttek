# Extension points

| Point | What an application gives | Where |
|---|---|---|
| `CatalogProvider`, `WritableCatalog` | Where the catalog comes from: code, a configuration, or a database the site edits | [The catalog](catalog.md#a-catalog-kept-elsewhere) |
| `Store\CopyStore`, `Store\CompletionStore`, `Pack\OpeningStore`, `Daily\DailyStore`, `Dice\ChoiceStore` | The storage of copies, completed sets, opened packs, the calendar of the day and the dice chosen | [Writing a store](writing-a-store.md) |
| `Daily\DateRule` | A computed date of the calendar of the item of the day — a week from Easter, the fourth Thursday of November | [The item of the day](item-of-the-day.md) |
| `Pack\SeedSource` | The seed of a pack's draw: a secure generator in production, a fixed seed in tests | [Packs](packs.md#opening-a-pack) |
| `CollectionObserver` | What is done when a copy is given or a set completed: a notification, a reward | [Copies and the album](copies-and-album.md) |
| `Psr\Clock\ClockInterface` | The clock the services read: the system's in production, a fixed one in tests | [Testing](testing.md) |

Every point is passed to the constructor of the service that uses it; the services keep no state of their own between
two calls.
