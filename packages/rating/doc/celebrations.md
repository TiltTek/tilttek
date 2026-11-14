# Celebrations

A rating that climbs is a moment to celebrate, and a new highest rating more so. The library says when; the page plays
it, with the celebration component of the PHIL UX kit or anything else.

## The events

| Event | When | Carries |
|---|---|---|
| `Event\RatingChanged` | A rated result is kept, for each of its members | `game`, `member`, `result` |
| `Event\PeakReached` | After `RatingChanged`, when the member's score goes above the highest they reached, once they played the rated games `celebrateAfter` asks for | `game`, `member`, `result` |

Every event carries identifiers only, never a rating: a listener reads the rating from the store, and shows it to the
member alone unless the member made it public. A friendly result and a replay dispatch nothing. `celebrateAfter`, 5 by
default, keeps the first games of a member — where a rating moves the most — from being celebrated each time.

An event is dispatched at least once: one whose listener failed is dispatched again when the result is recorded
again or delivered, see [Recording results](recording-results.md#the-events). A listener that plays a celebration or
awards something does it once per `key()`.

## With the PHIL UX kit

A listener keeps the moment for the member's next page — in the session, or as a notification — and the page plays the
kit's celebration once:

```twig
{% if rating_peak %}
    <twig:Celebration message="{{ 'rating.peak'|trans }}" effect="fireworks" :autoplay="true" />
{% endif %}
```

The library adds no component of its own and depends on no kit: the words and the moment are the application's.

## Accessibility

The kit's celebration says its message in a polite live region, draws nothing where motion is reduced, plays no sound
and lasts a few seconds at most. A celebration of a rating is shown to its member only; a public page celebrates
nothing about a private rating.
