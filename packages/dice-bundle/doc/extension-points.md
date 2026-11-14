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
        return new DieAppearance('Ember', '#aa0000', 'resin', '/images/ember.png', ink: '#ffd700', texture: 'dice/ember');
    }
}
```

Name the service in `tilttek_dice.appearance`. **The appearance never changes the randomness**: it dresses the dice the
server rolled. Fudge dice ask for 6 sides; custom dice carry their own faces and ask for none.

| Argument | Holds | Checked |
|---|---|---|
| `name` | The name of the set | Not blank |
| `color` | The colour of the dice | `#rrggbb` |
| `material` | A word for the stylesheet: `resin`, `metal`, `glass`… | — |
| `image` | An image of the set | — |
| `ink` | The colour of the numbers | `#rrggbb` |
| `texture` | A key the application resolves to its own picture, never an address | 1 to 128 characters among `a-z`, `0-9`, `.`, `_`, `/` and `-`, starting with a letter or a digit, without `..`, `//` or a final `/` |

A value that fails its check raises `Tilttek\Dice\Exception\InvalidDefinition` when the appearance is built. Pass `ink`
and `texture` by name.

**The numbers always read.** `readableInk()` gives the ink given, or, on a die with a colour and no ink, black or white,
whichever contrasts more with the colour: always at least 4.5:1, the contrast WCAG 2 asks of text. A die without a
colour has no computed ink: the stylesheet gives it. `Application\Contrast` measures the contrast of two colours, for an
application that checks the inks it accepts. `toArray()` gives the appearance as the detail of a roll shows it, the
readable ink included, ready for a JSON message.

## Dialects

A service extending `Tilttek\Dice\Dialect\Dialect` is tagged `tilttek_dice.dialect` by autoconfiguration and added to
the dialects; one with a built-in dialect's name replaces it.

## The journal

`Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository` is the journal's port: a roll found by its identifier, or
many at once in the order asked — at most `RecordedRollRepository::MANY`, 500 —, saved, listed by owner or by context
the latest first, and erased by owner. An application may alias it to an implementation of its own; the bundle's are on
Doctrine and in memory.

## A roll recorded

`DiceRolls` dispatches `Tilttek\DiceBundle\Application\Event\RollRecorded` after it saves each replayable roll, when the
application has an event dispatcher. The event carries identifiers only — the roll's, its owner's and its context's —:
a listener reads the roll from the journal, and a message it sends on, to Mercure or to a queue, carries the same.

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\DiceBundle\Application\Event\RollRecorded;

#[AsEventListener]
final class AnnounceRoll
{
    public function __invoke(RollRecorded $event): void
    {
        // $event->id, $event->owner, $event->context
    }
}
```

## The macros

`Tilttek\DiceBundle\Domain\Repository\DiceMacroRepository` is the port of the [macros](macros.md): a macro found by its
level, its owner and its name, saved, removed, listed by owner and erased by owner. An application may alias it to an
implementation of its own; the bundle's are on Doctrine and in memory. An application that keeps its macros elsewhere
altogether implements `Tilttek\Dice\Contract\Macros` and passes it to `DiceRoller::parse()`.
