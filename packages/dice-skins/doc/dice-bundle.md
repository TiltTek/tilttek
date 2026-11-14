# Rolling with tilttek/dice-bundle

`tilttek/dice-bundle` rolls the dice of a Symfony application and asks its `DiceAppearance` port how a member's dice
look. `DiceBundle\DiceSkinsAppearance` is that port's adapter: it gives the roller the appearance `DiceSkins` computes,
so the dice a member rolls wear the skin they chose or the die of a set they own.

The adapter needs `tilttek/dice-bundle` 0.2.0-alpha or later, whose `DieAppearance` carries the colour of the numbers
and the texture key, and whose port passes the viewer's accessibility. The library does not require the bundle: it suggests it, and the adapter is loaded only by an
application that declares it.

## What the roller receives

For a member and a number of sides, the adapter returns a `Tilttek\DiceBundle\Application\DieAppearance`:

| Argument | Value |
|---|---|
| `name` | The label of the skin, or the name of the die the member owns |
| `color` | The colour of the faces, `#rrggbb` in lower case |
| `material` | The material, one of the values of `Material`: `solid`, `translucent`, `pearl`… |
| `image` | Null: the library describes dice by colours, material and texture |
| `ink` | The colour of the numbers and pips, `#rrggbb` in lower case |
| `texture` | The texture key, `pearl/soft` for instance, or null |

The appearance is found as [Usage](usage.md) describes: the die of a set the member owns and chose, then the skin they
chose for that number of sides, the skin they chose for all their dice, and the site's default skin. A number of sides
under 2 or over `Skin::MAX_SIDES` gets null, and the roller shows its plain dice.

A default skin the catalog does not offer throws `SkinRefused`: it is a mistake of the configuration, not a member's
choice, and the adapter does not hide it.

## Declaring the adapter

The library ships no bundle: the application declares `DiceSkins`, its catalog and its store as services, then the
adapter, and names the adapter in the bundle's `appearance` option.

```yaml
# config/services.yaml
services:
    Tilttek\DiceSkins\Store\FixedSkinCatalog:
        factory: ['Tilttek\DiceSkins\Store\FixedSkinCatalog', 'withBase']

    Tilttek\DiceSkins\DiceSkins:
        arguments:
            $catalog: '@Tilttek\DiceSkins\Store\FixedSkinCatalog'
            $choices: '@App\Dice\DoctrineSkinChoiceStore'     # your Contract\SkinChoiceStore

    Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance:
        arguments:
            $skins: '@Tilttek\DiceSkins\DiceSkins'
```

```yaml
# config/packages/tilttek_dice.yaml
tilttek_dice:
    appearance: Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance
```

With autowiring, `DiceSkinsAppearance` only needs `DiceSkins` to be a service: declare the class and its catalog and
store, and name it in `tilttek_dice.appearance`.

`DiceSkins` and the adapter keep nothing between two calls: one instance of each serves every request of a worker.

## High contrast

The roller passes the viewer's accessibility to the port when the page gives it —
`dice_roll_detail(result, member, locale, 'high-contrast')` —, and the adapter follows it: high contrast for a viewer
who asked for it, the standard look for one who did not. Without it, the adapter gives the standard look, unless it is
built with `Accessibility::HighContrast`:

```yaml
services:
    app.dice_appearance.high_contrast:
        class: Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance
        arguments:
            $skins: '@Tilttek\DiceSkins\DiceSkins'
            $accessibility: !php/enum Tilttek\DiceSkins\Accessibility::HighContrast
```

In high contrast, the numbers stand out at 7:1 at least, the material is `solid` and there is no texture, as
[Accessibility](accessibility.md) explains. Where the application draws the dice itself, outside the roller, it calls
`DiceSkins::appearance()` with the viewer's `Accessibility`.
