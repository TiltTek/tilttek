# tilttek/stickers

**Virtual stickers a member places on their profile or messages: a catalogue with text alternatives and credits, owned
stickers, bounded placements, site limits, moderation, and sheets completed as sets.**

A site keeps a catalogue of stickers, each a reference to an image the application serves, with the text alternative
every sticker has, and its licence and attribution. Members are given stickers — as a reward, a purchase, a gift — and
place those they own on the surfaces the application lets them use: their profile, their messages, any resource it
names. A placement sits in the sticker layer of its surface, at a position, a scale and a rotation the site bounds;
the site limits how many stickers a surface holds and how many a member places. Moderation hides a placement, at once
through a moderator the application plugs or later by hand, and restores it.

Stickers never stand in the way of the content: a viewer can turn them off, and the library gives a template every
placement with the sticker's text alternative and credit. Sticker sheets and albums are sets of `tilttek/sets`,
completed from the stickers a member owns, with the rewards they declare.

The library stores no image and holds no state between two calls: the catalogue, the stickers owned and the placements
live behind ports, with in-memory adapters. It is a plain PHP library.

## Status

| Part | State |
|---|---|
| `Sticker`, `Contract\StickerCatalog`: image references, text alternatives, licences, attributions, retired stickers | Implemented and tested |
| `Stickers`: stickers granted and revoked, placed, moved, removed, hidden and restored, shown to a viewer | Implemented and tested |
| `Transform`, `Limits`, `PlacementRequest`: bounded positions, scales and rotations, the limits of a site, requests read from forms and JSON | Implemented and tested |
| `Contract\SurfacePolicy`, `Contract\PlacementModerator`, `Verdict`: whose surfaces, and moderation of new placements | Implemented and tested |
| `ViewerPreferences`, `PlacementView`: stickers turned off by a viewer, text alternatives and credits | Implemented and tested |
| `StickerSheets`: sheets and albums as sets of `tilttek/sets` | Implemented and tested |
| Events of every change, identifiers only | Implemented and tested |
| In-memory adapters of every port | Implemented and tested |
| A database adapter, a template or a Stimulus controller | Not provided: the application draws the layer and implements the stores |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [The catalogue](doc/catalogue.md) | Stickers, their images, text alternatives and credits, retiring, granting and revoking. |
| [Placing stickers](doc/placing-stickers.md) | Surfaces, the transform, the limits of a site, moving and removing, requests from an editor. |
| [Showing and moderating](doc/showing-and-moderating.md) | Accessibility, viewers who turn stickers off, moderation, events and erasure. |
| [Sheets and albums](doc/sheets.md) | Sets of stickers, their tiers and rewards. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `StickersException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz target and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\{PlacementRequest, Sticker, Stickers, ViewerPreferences};
use Tilttek\Stickers\Policy\InMemorySurfacePolicy;
use Tilttek\Stickers\Store\{InMemoryOwnershipStore, InMemoryPlacementStore, InMemoryStickerCatalog};

$catalog = new InMemoryStickerCatalog([
    new Sticker('red-dragon', 'stickers/red-dragon.webp', 'A red dragon curled up asleep', 'CC-BY-4.0', 'Art by A. Painter'),
]);
$member = ResourceReference::of('member', '1042');
$profile = ResourceReference::of('profile', '1042');
$surfaces = new InMemorySurfacePolicy();
$surfaces->assign($profile, $member);

$stickers = new Stickers($catalog, new InMemoryOwnershipStore(), new InMemoryPlacementStore(), $surfaces);
$stickers->grant($member, 'red-dragon');
$stickers->place($member, PlacementRequest::fromJson('{"sticker":"red-dragon","surface":"profile:1042","x":800,"y":120,"rotation":-10}'));

foreach ($stickers->onSurface($profile, new ViewerPreferences(showStickers: true)) as $view) {
    $view->alt();        // 'A red dragon curled up asleep'
    $view->credit();     // 'Art by A. Painter — CC-BY-4.0'
}
```

## Requirements

PHP 8.5 or later with `ext-mbstring` and `ext-sodium`; `phil/resource` 0.1, `psr/clock`, `psr/event-dispatcher` and
`tilttek/sets` 0.1.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
