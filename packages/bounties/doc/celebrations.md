# Celebrations

A bounty awarded is a moment to celebrate. The library says when; the page plays it, with the celebration component of
the PHIL UX kit or anything else. The library depends on no kit.

## The event

`Event\BountyAwarded` is dispatched once for each winner, when the bounty is settled, after the points are paid: it
carries the bounty, the winner, the points paid to them and their unit. `Event\BountyClosed` follows, with the state
and the points given back. A listener tells each winner, then the page shows the celebration:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\Bounties\Event\BountyAwarded;

#[AsEventListener]
final readonly class CelebrateBountyAwarded
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(BountyAwarded $awarded): void
    {
        $this->notifier->tell($awarded->winner, 'bounty.awarded', ['bounty' => $awarded->bounty, 'points' => $awarded->amount]);
    }
}
```

The event carries identifiers only: a listener that sends a Messenger message sends them, and the handler reads the
rest from the store.

## The page

The kit's recipe plays at once on the page that shows the winner their bounty:

```twig
<twig:Celebration message="{{ 'bounty.awarded'|trans({'%points%': payout.amount}) }}" effect="fireworks" :autoplay="true" />
```

or on a DOM event the page dispatches when it learns of the award, through Mercure for instance:

```twig
<twig:Celebration message="{{ 'bounty.awarded'|trans }}" data-action="bounty:awarded@window->celebration#play" />
```

## Accessibility

The kit's celebration says its message in a polite live region, draws nothing where motion is reduced
(`prefers-reduced-motion`), plays no sound, lasts a few seconds at most and never flashes more than three times a
second. The message names what was won — the bounty and its points — so that it means the same to a screen reader.
