# Reference

| Type | Role |
|---|---|
| `Collections` | Copies given, sets given whole, base sets, moves, copies taken back, the album |
| `Catalog`, `CatalogReader` | The definitions of a collection; read from plain values |
| `CatalogProvider`, `FixedCatalog`, `WritableCatalog`, `DefinitionKind` | Where the catalog comes from; a catalog edited one definition at a time |
| `Rarity`, `Item`, `CollectibleDie`, `ItemSet`, `SetKind`, `Pack`, `Reward` | The definitions |
| `Names`, `Texts` | The codes; texts by locale |
| `Odds` | The published odds of a draw, and the draw |
| `CollectionKey`, `Source`, `Copy`, `CopyMove` | The key of a copy; how it came; a copy; a copy changing hands |
| `Album`, `Holding`, `SetProgress` | A member's album; the copies of an item; the progress of a set |
| `SetCompletion`, `SetCompleted`, `CollectionObserver` | A set completed once; what is told; who is told |
| `MemberData`, `Erasure` | The export and erasure of a member; what it removed |
| `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening` | Packs committed, opened and verified; a pack committed; an opening |
| `Pack\SeedSource`, `Pack\SecureSeeds` | Where seeds come from; secure seeds |
| `Pack\OpeningStore`, `Pack\InMemoryOpeningStore` | Where the openings are kept |
| `Daily\DailyCalendar`, `Daily\DailyPick`, `Daily\PickOrigin` | The calendar of the item of the day; a day's item; why |
| `Daily\DateRule`, `Daily\FixedDate`, `Daily\Days` | A rule of the calendar; a fixed date; days written `Y-m-d` |
| `Daily\Daily`, `Daily\DailyProjection` | The item of the day claimed; on the activity |
| `Daily\DailyStore`, `Daily\InMemoryDailyStore` | Where the calendar is kept |
| `Dice\DiceShelf`, `Dice\DieChoice` | The dice a member owns and chose; a choice |
| `Dice\ChoiceStore`, `Dice\InMemoryChoiceStore` | Where the choices are kept |
| `Store\CopyStore`, `Store\InMemoryCopyStore` | Where the copies are kept |
| `Store\CompletionStore`, `Store\InMemoryCompletionStore` | Where the sets completed are kept |
| `Exception\CollectionsException` and its three exceptions | [Exceptions](exceptions.md) |
