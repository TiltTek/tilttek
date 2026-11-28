# Celebrating

## The next celebration

`next($member, $deviceReducesMotion)` returns what the window shows next — a `Celebration` — or none when nothing
waits or the member turned celebrations off:

| Field | What it holds |
|---|---|
| `group` | The first pending `Group`: its source, kind, first entries and count |
| `motion` | The `Motion` the window plays: `Full`, `Reduced` or `None` |
| `sound` | Whether it plays a sound |
| `remaining` | How many groups wait after this one |

Once the window was shown, the page marks the group seen — `markSeen($member, $celebration->group->sequences())`, or
`markGroupSeen()` to include the entries it did not show — and asks `next()` again while `remaining` is above zero.

## Preferences

`choose($member, $preferences)` keeps a member's `Preferences`; `preferences($member)` reads them, the defaults when
the member chose none:

| Field | Default | What it decides |
|---|---|---|
| `celebrate` | `true` | Whether a window celebrates; turned off, `next()` returns none while `pending()`, `count()` and the history read the inbox as before |
| `motion` | `Motion::Full` | How much the window moves |
| `sound` | `false` | Whether the window plays a sound: never by default |

The page tells `next()` whether the member's device asks for reduced motion — the `prefers-reduced-motion` media
feature, read in the browser and sent with the request, or a cookie the page sets —: it reduces `Full` to `Reduced`,
and never adds motion to a member who chose `Reduced` or `None`. `Preferences::motionFor()` gives the same answer to
code that builds its own window.

| The member's `motion` | The device asks for reduced motion | The window plays |
|---|---|---|
| `Full` | No | `Full` |
| `Full` | Yes | `Reduced` |
| `Reduced` | Either | `Reduced` |
| `None` | Either | `None` |

The window itself — light CSS and SVG, a fade for `Reduced`, a still card for `None`, the focus moved to it and back,
announced to screen readers — belongs to the application or to a bundle.

## Listeners for the rewards packages

The inbox depends on no rewards package: the application gives each package it uses a listener that turns its events
into achievements. A listener for the badges of `tilttek/badges`:

```php
use Tilttek\Badges\Award;
use Tilttek\Badges\AwardObserver;
use Tilttek\Badges\Badge;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebrations;

final readonly class CelebrateBadges implements AwardObserver
{
    public function __construct(private Celebrations $celebrations)
    {
    }

    public function awarded(Award $award, Badge $badge): void
    {
        $this->celebrations->receive(new Achievement(
            $award->member,
            'badges',
            'badge',
            'badge.'.$award->badge,
            $award->badge.'#'.$award->number,
            $award->awardedAt,
        ));
    }

    public function revoked(Award $award, Badge $badge): void
    {
        $this->celebrations->withdraw($award->member, 'badges', $award->badge.'#'.$award->number);
    }
}
```

The occurrence carries the award's number, so a repeatable badge earned twice is celebrated twice, and a notice told
again is kept once. A level of `tilttek/progression` gives `progression`, `level`, the track's key and an occurrence
made of the track, the season and the level reached; a quest of `tilttek/quests`, `quests`, `quest`, the quest's key and
its completion. Each listener writes its source and kinds once; the application's translations give their messages.
