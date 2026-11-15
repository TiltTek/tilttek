# Celebrations

A challenge won is a moment to celebrate. The library says when; the page plays it, with the celebration component of
the PHIL UX kit or anything else. The library depends on no kit.

## The event

`Event\ChallengeWon` is dispatched once, when a proof is accepted, after the prize is paid: it carries the challenge,
the winner, the proof, the prize and its unit. A listener tells the winner and the participants, then the page shows
the celebration:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tilttek\FriendChallenges\Event\ChallengeWon;

#[AsEventListener]
final readonly class CelebrateChallengeWon
{
    public function __construct(private Notifier $notifier)
    {
    }

    public function __invoke(ChallengeWon $won): void
    {
        $this->notifier->tell($won->winner, 'challenge.won', ['challenge' => $won->challenge, 'prize' => $won->prize]);
    }
}
```

The event carries identifiers only: a listener that sends a Messenger message sends them, and the handler reads the
rest from the store.

## The page

The kit's recipe plays at once on the page that shows the challenge won:

```twig
{% if challenge.state.value == 'won' %}
    <twig:Celebration message="{{ 'challenge.won'|trans({'%prize%': challenge.prize.amount}) }}" effect="confetti" :autoplay="true" />
{% endif %}
```

or on a DOM event the page dispatches when it learns of the win, through Mercure for instance:

```twig
<twig:Celebration message="{{ 'challenge.won'|trans }}" data-action="challenge:won@window->celebration#play" />
```

## Accessibility

The kit's celebration says its message in a polite live region, draws nothing where motion is reduced
(`prefers-reduced-motion`), plays no sound, lasts a few seconds at most and never flashes more than three times a
second. The message names what was won — the challenge and its prize — so that it means the same to a screen reader.
