# Extension points

## A member's dice

`Tilttek\DiceBundle\Application\Port\DiceAppearance` gives, for a member and a number of sides, the appearance of the
dice to show — the set the member owns, for instance —, or null for the plain ones:

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;

final class OwnedDice implements DiceAppearance
{
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        return new DieAppearance('Ember', '#aa0000', 'resin', '/images/ember.png');
    }
}
```

Name the service in `tilttek_dice.appearance`. A `DieAppearance` has a name, a colour written `#rrggbb`, a material and
an image. **The appearance never changes the randomness**: it dresses the dice the server rolled. Fudge dice ask for 6
sides; custom dice carry their own faces and ask for none.

## Dialects

A service extending `Tilttek\Dice\Dialect\Dialect` is tagged `tilttek_dice.dialect` by autoconfiguration and added to
the dialects; one with a built-in dialect's name replaces it.

## The journal

`Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository` is the journal's port. An application may alias it to an
implementation of its own; the bundle's are on Doctrine and in memory.

## The macros

`Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository` is the port of the [macros](macros.md): a macro found by its
level, its owner and its name, saved, removed, listed by owner and erased by owner. An application may alias it to an
implementation of its own; the bundle's are on Doctrine and in memory. An application that keeps its macros elsewhere
altogether implements `Tilttek\Dice\Contract\Macros` and passes it to `DiceRoller::parse()`.
