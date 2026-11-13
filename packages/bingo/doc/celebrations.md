# Celebrations

A full line, a full card: a moment to celebrate. The library says when; the page plays it, with the celebration
component of the PHIL UX kit or anything else.

## The events

| Event | When | Carries |
|---|---|---|
| `Event\BingoWon` | A card wins, once per card | `game`, `serial`, `player` — null for a printed card —, `pattern` |
| `Event\SquareTicked` | A gesture ticks a square of a card | `game`, `serial`, `square` |
| `Event\SquareCalled` | The host calls a square, the first time | `game`, `square` |

Every event carries identifiers only: a listener that pushes the card again, gives a prize or sends a Messenger message
reads the rest from the store. [Live updates](live-updates.md) push them to the players' screens.

## The card template

`templates/card.html.twig` shows a card from a `View\CardView`, built by `CardView::of($bingo, $game, $card)`: a table
of its rows, each cell with its label — or the free centre — and, to screen readers, whether it is ticked. Register the
folder under a namespace of the application:

```yaml
# config/packages/twig.yaml
twig:
    paths:
        '%kernel.project_dir%/vendor/tilttek/bingo/templates': TilttekBingo
```

The variable `labels` gives the words of the page in its language: `caption`, `ticked` and `free`, in English by
default. The block `won`, empty by default, is drawn only for a card that won. With the kit's celebration:

```twig
{% embed '@TilttekBingo/card.html.twig' with {card: view, labels: {caption: 'bingo.card'|trans, ticked: 'bingo.ticked'|trans, free: 'bingo.free'|trans}} %}
    {% block won %}<twig:Celebration message="{{ 'bingo.won'|trans }}" effect="fireworks" :autoplay="true" />{% endblock %}
{% endembed %}
```

## The Stimulus controller

The template carries the controller `tilttek--bingo--card`, from the package's `assets/package.json`. Once the card
won, it dispatches the DOM event `bingo:won` on the window, its detail holding `game`, `serial` and `pattern`, once per
card in a browser tab; where the browser refuses storage, it says it every time. The kit's recipe plays on it:

```twig
<twig:Celebration message="{{ 'bingo.won'|trans }}" data-action="bingo:won@window->celebration#play" />
```

The recipe is the kit's own, used as it is: the library adds no component of its own.

## Accessibility

Each cell says its label and, in a visually hidden text, that it is ticked; the caption names the card's serial. The
kit's celebration says its message in a polite live region, draws nothing where motion is reduced, plays no sound and
lasts a few seconds at most.
