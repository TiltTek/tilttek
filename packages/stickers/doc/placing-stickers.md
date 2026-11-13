# Placing stickers

## Surfaces

A surface is any resource the application names by a `ResourceReference`: `profile:1042`, `message:0192f3c4`. The
`Contract\SurfacePolicy` the application gives says whether a holder may place stickers on it — their own profile,
their own messages; there is no permissive default. `Policy\InMemorySurfacePolicy` assigns each surface to its holder.

## The transform

A `Transform` says where a sticker sits in the sticker layer of its surface and how:

| Value | Unit | Absolute bounds |
|---|---|---|
| `x`, `y` | Thousandths of the layer's width and height, from the top left corner, to the sticker's centre | 0 to 1 000 |
| `scale` | Percent of the sticker's natural size | 10 to 400 |
| `rotation` | Whole degrees, clockwise | -180 to 180 |

Positions relative to the layer keep a placement where it was put whatever the width of the screen.

## The limits of a site

`Limits` narrows the transform and counts the placements:

| Limit | Default | Absolute bounds |
|---|---|---|
| `perSurface` | 12 | 1 to 100 |
| `perHolder` | 500 | 1 to 10 000 |
| `minScale`, `maxScale` | 50, 200 | 10 to 400, the smallest first |
| `maxRotation` | 45, either way | 0 to 180 |

Hidden placements count toward the limits until they are removed.

## Placing

`Stickers::place($holder, $request)` places a sticker and dispatches `Event\StickerPlaced` when:

1. the sticker is in the catalogue and not retired;
2. the holder owns it;
3. the policy lets the holder use the surface;
4. the transform is within the site's limits;
5. the surface and the holder are under their limits.

Otherwise it throws `StickerRefused`, and nothing is stored. The placement gets an identifier of 32 hexadecimal
characters from a secure source, and its moment in UTC. A moderator, when there is one, reviews it before it is
stored — see [Showing and moderating](showing-and-moderating.md#moderation).

## Moving and removing

`Stickers::move($holder, $placement, $transform)` moves one of the holder's placements within the site's limits;
`Stickers::remove($holder, $placement)` removes it and dispatches `Event\PlacementRemoved`. Another holder's placement
throws `StickerRefused`; an unknown one throws `UnknownPlacement`.

## Requests from an editor

`PlacementRequest::fromJson()` reads what a sticker editor sends, at most 4 KiB and one level of nesting, and
`fromArray()` what a form gives:

```json
{"sticker": "red-dragon", "surface": "profile:1042", "x": 800, "y": 120, "scale": 120, "rotation": -10}
```

`scale` defaults to 100 and `rotation` to 0. An unknown key, a missing key, a value of the wrong type, a surface that is
not a resource reference or a value outside the absolute bounds throws `InvalidSticker`, never another exception; the
site's limits are checked when the request is placed.

## Concurrency

A store shared by several processes counts and adds in one transaction — a lock on the surface and on the holder —,
so that two placements at the same moment never pass a limit together.
