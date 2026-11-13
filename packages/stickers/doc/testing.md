# Testing

## The library's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a network or a database: every bound of a sticker, a transform and
limits, image references that would be scripts or inline data refused, texts trimmed and checked, holders and surfaces
referenced by an e-mail address refused, requests read from JSON with everything else refused, placements granted,
placed, refused by each rule and each limit, moved, removed, hidden by a moderator or by hand and restored, viewers who
turn stickers off, stickers that left the catalogue, revoking and erasure, the events, the stores and sticker sheets
completed through `tilttek/sets`. In the monorepo, Infection holds the library at 100 % of covered mutants.

`tests/Fuzz/placement.php` is the fuzz target of a placement request: any text reads as a request within the absolute
bounds, which the limits of a site accept or refuse with `StickerRefused`, or is refused with `InvalidSticker`, never
another exception. `composer fuzz` runs it.

`tests/Benchmark/StickersBench.php` measures the fullest surface a site allows — 100 placements — shown to a viewer,
and a placement request read from JSON.

## Testing an application

Give `Stickers` the in-memory adapters, a surface policy with the surfaces assigned, a clock of your own and a
dispatcher that keeps the events:

```php
$surfaces = new InMemorySurfacePolicy();
$surfaces->assign($profile, $member);
$stickers = new Stickers($catalog, new InMemoryOwnershipStore(), new InMemoryPlacementStore(), $surfaces, new Limits(), $frozenClock, $recordingDispatcher);
```
