# Reference

| Type | Role |
|---|---|
| `Stickers` | Grants and revokes, places, moves and removes, hides and restores, shows a surface and erases |
| `StickerSheets` | Sticker sheets and albums as sets of `tilttek/sets`, measured against the stickers owned |
| `Sticker` | A sticker of the catalogue: image reference, text alternative, licence, attribution, retired |
| `Placement` | A sticker placed by a holder on a surface, with its transform, its moment in UTC and its moderation |
| `PlacementRequest` | What a holder asks to place, read from a form or JSON |
| `Transform` | The position, scale and rotation of a placement, within absolute bounds |
| `Limits` | The limits of a site: per surface, per holder, scales and rotation |
| `Verdict` | What a moderator says of a new placement: shown, or hidden for a reason |
| `ViewerPreferences` | Whether a viewer sees stickers |
| `PlacementView` | A placement with its sticker, its text alternative and its credit |
| `Contract\StickerCatalog` | The stickers of the site |
| `Contract\OwnershipStore` | The stickers each holder owns |
| `Contract\PlacementStore` | The placements |
| `Contract\SurfacePolicy` | Which surfaces each holder may use |
| `Contract\PlacementModerator` | The moderation of new placements |
| `Store\InMemoryStickerCatalog`, `Store\InMemoryOwnershipStore`, `Store\InMemoryPlacementStore` | The adapters in memory |
| `Policy\InMemorySurfacePolicy` | Surfaces assigned to their holders, in memory |
| `Event\StickerGranted`, `StickerRevoked`, `StickerPlaced`, `PlacementHidden`, `PlacementRestored`, `PlacementRemoved` | See [Showing and moderating](showing-and-moderating.md#events) |
| `Exception\StickersException` | The interface of every exception of the library |
| `Exception\InvalidSticker`, `StickerRefused`, `UnknownPlacement` | See [Exceptions](exceptions.md) |

`Guard` is internal.
