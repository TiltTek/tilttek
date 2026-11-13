# Exceptions

Every exception of the library implements `Tilttek\Stickers\Exception\StickersException`. `StickerSheets` lets the
exceptions of `tilttek/sets` through: `UnknownSet` for a sheet never defined.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidSticker` | A code, an image reference, a text alternative, a licence, an attribution, a transform, limits, a holder, a surface or a moderation reason is refused, or a placement request does not read | `The text alternative of a sticker is 1 to 250 characters of UTF-8, without control characters.` |
| `StickerRefused` | A sticker unknown, retired or not owned, a surface not the holder's, a transform outside the site's limits, a limit reached, or a placement not the holder's | `A surface holds at most 12 stickers on this site.` |
| `UnknownPlacement` | A placement that does not exist, or no longer does | `There is no placement "4f1c…".` |

`InvalidSticker` extends `InvalidArgumentException`; the others extend `RuntimeException`.
