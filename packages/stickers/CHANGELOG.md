# Changelog

All notable changes to `tilttek/stickers` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- `Sticker`: a sticker of the catalogue with a reference to its image — never a `javascript:`, `vbscript:`, `data:` or
  `file:` one —, a required text alternative of 1 to 250 characters, an optional licence and attribution, and its
  retirement.
- `Stickers`: stickers granted once and revoked with their placements; placed on the surfaces the policy lets each
  holder use, when owned, not retired and within the site's limits; moved and removed by their holder only; hidden for
  a reason and restored by moderation; shown to a viewer, in placing order, unless the viewer turned them off; the
  erasure of a holder and of a surface.
- `Transform` and `Limits`: positions in thousandths of the sticker layer, scales of 10 to 400 percent and rotations
  of -180 to 180 degrees, narrowed by the site, which also limits the stickers of a surface (12 by default, 100 at
  most) and of a holder (500 by default, 10 000 at most).
- `PlacementRequest`: a request read from a form or from JSON of at most 4 KiB, every fault an `InvalidSticker`.
- `Placement`: a placement with an identifier from a secure source and its moment in UTC.
- `Contract\SurfacePolicy`, `Contract\PlacementModerator` and `Verdict`: whose surfaces, with no permissive default, and
  a new placement hidden at once for a reason.
- `ViewerPreferences` and `PlacementView`: a viewer who turns stickers off, and each placement shown with the sticker's
  text alternative and credit.
- `StickerSheets`: sticker sheets and albums as sets of `tilttek/sets`, with their tiers and rewards, completed once
  from the stickers a holder owns.
- `Event\StickerGranted`, `Event\StickerRevoked`, `Event\StickerPlaced`, `Event\PlacementHidden`,
  `Event\PlacementRestored` and `Event\PlacementRemoved`, identifiers only.
- Holders and surfaces referenced by a stable identifier, never by an e-mail address.
- `Contract\StickerCatalog`, `Contract\OwnershipStore`, `Contract\PlacementStore` and their adapters in memory, with
  `Policy\InMemorySurfacePolicy`.
- The fuzz target of a placement request and the benchmarks of a full surface.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
