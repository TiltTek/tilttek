# Changelog

All notable changes to `tilttek/collections` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Names`, `Texts`, `Rarity`, `CollectibleDie` and `Reward`: codes of rarities, items, sets, packs and reward kinds;
  texts by locale read with the language and English as fallbacks; rarities weighing 1 to 10^6; the sides, colour and
  material of a die; rewards declared, never executed.
- `Item`, `SetKind`, `ItemSet` and `Pack`: items with a translated name and description, an image, a rarity, up to 20
  sets, bound or tradable, and the die they are; sets given item by item, on arrival, for points or paid, with up to 20
  rewards; packs of 1 to 50 items drawn from some sets with weights of their own.
- `Catalog`, `CatalogReader`, `CatalogProvider`, `FixedCatalog`, `WritableCatalog` and `DefinitionKind`: the catalog
  from plain values, unknown keys refused, every reference checked — an item's rarity and sets, a pack's sets and
  weights —, a set allowed to hold no item yet, provided fixed or edited one definition at a time.
- `Odds`: the published chance of each rarity and item of a draw, and the draw itself — a rarity by weight, then an
  item of that rarity — from an injected randomizer.
- `CollectionKey`, `Source`, `Copy` and `CopyMove`: copies identified by the key they were given under, so that a key
  gives one copy, with their source, moment, binding and whether they changed hands; keys the library derives carry
  `#`, which application keys cannot.
- `CopyStore`, `CompletionStore`, `Pack\OpeningStore`, `Dice\ChoiceStore` and `Daily\DailyStore`, with their in-memory
  implementations: copies written once per identifier, reassigned from their owner only, taken back unless traded,
  forgotten by source from a moment; sets completed once per member; packs opened by key; dice chosen per member and
  number of sides; the calendar by day. The implementations in memory empty themselves with `reset()`, for a
  long-running process.
- `Collections`, `Album`, `Holding`, `SetProgress`, `SetCompletion`, `SetCompleted` and `CollectionObserver`: copies
  given under a key — one item, a set whole, every base set on arrival —, a key that gave something else refused,
  moves of a trade all or nothing, copies taken back, the album with doubles and the progress of each set, and a set
  completed for the first time told with its rewards.
- `Pack\Packs`, `Pack\PendingOpening`, `Pack\PackOpening`, `Pack\SeedSource` and `Pack\SecureSeeds`: packs committed
  with a secret seed of 16 to 64 bytes from the source, its commitment shown, then opened once per key with the
  client seed of 1 to 256 bytes the member gives afterwards, `Xoshiro256StarStar` seeded by the HMAC-SHA256 of the
  client seed and the key under the seed, both seeds and the commitment kept, and the opening replayed to verify it.
- `Daily\DateRule`, `Daily\FixedDate`, `Daily\Days`, `Daily\PickOrigin`, `Daily\DailyPick` and `Daily\DailyCalendar`:
  the item of a day kept, else put forward by the first rule that speaks of it — fixed dates every year or once, rules
  of the application's own —, else drawn by rarity from the day and a salt; the calendar generated ahead up to 3,660
  days, chosen days kept, and released.
- `Daily\Daily` and `Daily\DailyProjection`: the item of the day claimed once per member and day in the calendar's
  timezone, by a projection of `tilttek/activity` on the verbs the site names, taken back when its activity is
  cancelled, forgotten before a rebuild unless traded.
- `Dice\DiceShelf` and `Dice\DieChoice`: the dice a member owns, the one they choose for each number of sides, and the
  plain dice again once it is traded away.
- `MemberData` and `Erasure`: the export of a member's copies, completed sets, opened packs and chosen dice, and their
  erasure.
- `CollectionsException`, implemented by `InvalidCollection`, `NotFound` and `CollectionRefused`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
