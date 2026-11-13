# Showing and moderating

## Showing a surface

`Stickers::onSurface($surface, $viewer)` gives the placements a viewer sees, in the order they were placed — the
latest drawn on top —, each as a `PlacementView` with its placement and its sticker. It leaves out:

- every placement, when the viewer turned stickers off with `ViewerPreferences::stickersOff()`;
- a placement hidden by moderation;
- a placement whose sticker left the catalogue.

## Accessibility

Stickers decorate a surface; they never stand in the way of what it says. The library gives what a template needs to
draw them so:

- every sticker has a text alternative, `PlacementView::alt()`, for the image's `alt`, and a credit,
  `PlacementView::credit()` — the attribution, then the licence —, for a caption or a title;
- a viewer can turn stickers off: keep the choice with the viewer's preferences and pass it to `onSurface()`, which
  then gives nothing;
- positions are relative to a sticker layer, which the template draws beside or over a margin of the surface, never
  over its text, and through which clicks pass;
- rotations and scales are bounded by the site, and the library animates nothing.

## Moderation

A `Contract\PlacementModerator` reviews each new placement before it is stored and returns a `Verdict`:
`Verdict::show()`, or `Verdict::hide('review')` for a reason the application names. A hidden placement is stored,
told by `Event\StickerPlaced` with `hidden: true`, and shown to no one.

`Stickers::hide($placement, $reason)` hides a placement later — a report handled, a review — and dispatches
`Event\PlacementHidden`; `Stickers::restore($placement)` shows it again and dispatches `Event\PlacementRestored`. The
application checks who may moderate before it calls them.

## Events

Every event carries identifiers and small values only, never a sticker's texts:

| Event | Told when |
|---|---|
| `Event\StickerGranted` | A holder is given a sticker they did not own |
| `Event\StickerRevoked` | A sticker is taken back, with the number of placements removed |
| `Event\StickerPlaced` | A sticker is placed, shown or hidden |
| `Event\PlacementHidden`, `Event\PlacementRestored` | Moderation hides or restores a placement |
| `Event\PlacementRemoved` | A holder removes one of their placements |

## Erasure

`Stickers::eraseHolder($holder)` removes a holder's placements and the stickers they own, for the erasure of a member,
and returns how many of each. `Stickers::eraseSurface($surface)` removes the placements of a profile or a message
deleted. Completions of sticker sheets live in `tilttek/sets`: `Sets::eraseHolder()` removes them.
