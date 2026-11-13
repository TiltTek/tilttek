# The catalogue

## Stickers

A `Sticker` holds:

| Field | Shape |
|---|---|
| `code` | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit |
| `image` | A reference to the image, 1 to 2 048 bytes without whitespace: an asset path, a key of the media library, an address |
| `alt` | The text alternative, required: 1 to 250 characters, trimmed, without control characters |
| `licence` | Optional: 1 to 100 characters, such as an SPDX identifier, `CC-BY-4.0` |
| `attribution` | Optional: 1 to 500 characters, the credit the licence asks for |
| `retired` | A retired sticker stays with its owners and where it is placed, but is placed no more |

The library never reads, stores nor checks the image: the application resolves the reference and serves the file. A
reference that starts with `javascript:`, `vbscript:`, `data:` or `file:` is refused, so that an image reference never
becomes a script, inline data or a local file in a template.

Write the text alternative for someone who does not see the image: what it shows, not its file name — *A red dragon
curled up asleep*, never *dragon.webp*.

`Sticker::retire()` gives the same sticker, retired; the catalogue keeps it so that its placements are still drawn.

## The catalogue port

`Contract\StickerCatalog` gives a sticker by its code and every sticker in the catalogue's order.
`Store\InMemoryStickerCatalog` takes them whole, from the application's configuration, and refuses a code given twice.

## Granting and revoking

`Stickers::grant($holder, $code)` gives a sticker of the catalogue to a holder and dispatches `Event\StickerGranted`;
it returns false when they own it already, and refuses a sticker that is not in the catalogue. A holder owns each
sticker once.

`Stickers::revoke($holder, $code)` takes it back, removes every placement of it the holder made, and dispatches
`Event\StickerRevoked` with the number removed. `Stickers::owned($holder)` lists what a holder owns, in the order it
was given, leaving out a sticker that left the catalogue.

A holder is a `ResourceReference` with a stable identifier — `member:1042` —: an identifier with `@`, plain or
percent-encoded, is refused, so that an e-mail address never becomes the key of what a member owns.
