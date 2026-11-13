# Sheets and albums

A sticker sheet, or an album, is a set of `tilttek/sets` whose members are sticker codes. Define it with the sets of
the application, tiers and rewards included:

```php
use Tilttek\Sets\{Reward, SetDefinition, Tier};

$sets->define(new SetDefinition('dragons-sheet', 1, ['red-dragon', 'blue-dragon', 'green-dragon', 'gold-dragon'],
    tiers: [new Tier('first-two', required: 2)],
    rewards: [new Reward('sticker', 'dragon-queen')],
));
```

`StickerSheets` measures a sheet against the stickers a holder owns:

- `progress($holder, $sheet)` gives the `Tilttek\Sets\Progress` of the sheet, recording nothing;
- `record($holder, $sheet)` records the completion of the sheet and of its tiers the first time they are reached, and
  `tilttek/sets` dispatches `SetCompleted` and `TierReached` with the rewards declared.

Call `record()` for the sheets a sticker belongs to when you hear `Event\StickerGranted`. A completion is never taken
back: a holder who loses a sticker keeps the sheet completed. A reward of kind `sticker`, as above, is the
application's to grant, by `Stickers::grant()`.
