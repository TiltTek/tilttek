# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `phil/resource` 0.1, whose `ResourceReference` names a member.
- `tilttek/collections` 0.1, whose shelf of dice gives the dice a member owns.
- Optionally, `tilttek/dice-bundle` 0.2.0-alpha or later, whose roller wears the appearance through
  `DiceBundle\DiceSkinsAppearance`, as [Rolling with tilttek/dice-bundle](dice-bundle.md) explains.

## Installing

```sh
composer require tilttek/dice-skins
```

## Wiring the library

`DiceSkins` takes the catalog of skins, the store of the members' choices, and optionally the owned dice and the key
of the default skin:

```php
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\Collections\ShelfOwnedDice;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;

$skins = new DiceSkins(
    FixedSkinCatalog::withBase($siteSkinsJson),          // Contract\SkinCatalog: the base skins and the site's own
    $choices,                                            // Contract\SkinChoiceStore: the members' choices
    new ShelfOwnedDice($collections, $shelf, 'en'),      // Contract\OwnedDice: Store\NoOwnedDice by default
    BaseSkins::DEFAULT,                                  // the key of the default skin: ivory
);
```

`DiceSkins` keeps nothing between two calls, so one instance serves every request of a worker.
`InMemorySkinChoiceStore` keeps the choices in the process, for tests and scripts; an application keeps them in its
own storage, one row per member and number of sides, as [Extension points](extension-points.md) describes.
