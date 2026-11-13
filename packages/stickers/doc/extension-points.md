# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\StickerCatalog` | The stickers of the site; `Store\InMemoryStickerCatalog` takes them whole | [The catalogue](catalogue.md#the-catalogue-port) |
| `Contract\OwnershipStore` | The stickers each holder owns; `Store\InMemoryOwnershipStore` keeps them in memory | [The catalogue](catalogue.md#granting-and-revoking) |
| `Contract\PlacementStore` | The placements; `Store\InMemoryPlacementStore` keeps them in memory | [Placing stickers](placing-stickers.md#concurrency) |
| `Contract\SurfacePolicy` | Which surfaces each holder may use; `Policy\InMemorySurfacePolicy` assigns them | [Placing stickers](placing-stickers.md#surfaces) |
| `Contract\PlacementModerator` | The moderation of new placements | [Showing and moderating](showing-and-moderating.md#moderation) |
| `Limits` | The limits of the site | [Placing stickers](placing-stickers.md#the-limits-of-a-site) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each change | [Showing and moderating](showing-and-moderating.md#events) |
| `Psr\Clock\ClockInterface` | The time of the placements, kept in UTC | [Installation](installation.md#wiring-the-library) |
| `Tilttek\Sets\Sets` | The sets the sticker sheets are | [Sheets and albums](sheets.md) |

Every point is passed to a constructor; `Stickers` and `StickerSheets` keep no state between two calls.
