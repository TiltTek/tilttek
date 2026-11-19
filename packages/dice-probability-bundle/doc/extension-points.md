# Extension points

## Templates

Override the templates as for any bundle, under `templates/bundles/TilttekDiceProbabilityBundle/`:

| Template | What it draws |
|---|---|
| `probabilities.html.twig` | The comparison, then each distribution |
| `_distribution.html.twig` | One distribution: its summary, its chart and its table |
| `_probability.html.twig` | One probability: its percentage and its fraction |
| `components/Distribution.html.twig` | The component: its fields, then the results |

An override keeps the table: it is the accessible form of the chart. It names the components of the kit through
`tilttek_dice_probability_kit()`, so that it follows the application's subdirectory.

## The recipes of the UX kit

The recipes `ux:install` copied are the application's: change their Twig, or install them from
`github.com/parler-haut-interagir-librement/ux-kit-shadcn` or another kit with the same names and props. The bundle
needs `Alert`, `Card`, `Chart`, `Field`, `Input`, `RadioGroup` and `Table`, with their parts.

## Translations

The words are in the `tilttek_dice_probability` domain, in English and French; add a file
`translations/tilttek_dice_probability.<locale>.yaml` for another language, with the keys of the bundle's files. The
reasons of the bundle's refusals are under `refusal`: `groups`, `outcomes` with `%outcomes%` and `%limit%`, and
`throttled`.

## The rate limit

`rate_limit.limiter` names a rate limiter factory of the application, any `RateLimiterFactoryInterface`: a policy of
`framework.rate_limiter`, a compound one, or one of the application's own. The component asks it for one limiter per
client network, by a keyed digest, and consumes one token per computation.

## Events

The events of the application implement `Tilttek\DiceProbability\Event\Event` and go to `DiceStatistics::chance()`:
see the extension points of `tilttek/dice-probability`.
