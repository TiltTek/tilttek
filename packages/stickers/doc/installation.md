# Installation

## Requirements

- PHP 8.5 or later, with `ext-mbstring` and `ext-sodium`.
- `phil/resource` 0.1, whose `ResourceReference` names holders and surfaces.
- `tilttek/sets` 0.1, for sticker sheets and albums.
- `psr/clock` and `psr/event-dispatcher`: the clock of the placements and the dispatcher of the events, both optional.

## Installing

```sh
composer require tilttek/stickers
```

## Wiring the library

```php
use Tilttek\Stickers\{Limits, Stickers, StickerSheets};

$stickers = new Stickers(
    $catalog,       // Contract\StickerCatalog: the stickers of the site
    $ownerships,    // Contract\OwnershipStore: the stickers each holder owns
    $placements,    // Contract\PlacementStore: where the stickers are placed
    $surfaces,      // Contract\SurfacePolicy: which surfaces each holder may use
    new Limits(perSurface: 12, perHolder: 500, minScale: 50, maxScale: 200, maxRotation: 45),
    $clock,         // a PSR-20 clock; the system's in UTC by default
    $dispatcher,    // a PSR-14 dispatcher, told of each change; null to dispatch nothing
    $moderator,     // Contract\PlacementModerator, which may hide a new placement; null for none
);

$sheets = new StickerSheets($sets, $ownerships);   // $sets: a Tilttek\Sets\Sets
```

`Stickers` and `StickerSheets` keep nothing between two calls, so one instance serves every request of a worker. The
in-memory adapters keep everything in the process, for tests, scripts and a catalogue declared in configuration.
