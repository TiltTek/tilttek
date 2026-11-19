# Templates

## Twig functions

| Function or filter | What it gives |
|---|---|
| `tilttek_dice_distribution(expression, locale = null, rounding = null)` | The `Distribution` of an expression |
| `tilttek_dice_versus(first, second, locale = null, rounding = null)` | The `Versus` of two expressions |
| `tilttek_dice_probabilities(expression, versus = null, show = 'exactly', locale = null, rounding = null)` | The table and the chart, rendered |
| `fraction\|tilttek_dice_fraction(digits = 12)` | A probability in its lowest terms, `4/9`; null when its denominator is longer than the digits, or a term longer than twice the digits before it is reduced |

```twig
{% set distribution = tilttek_dice_distribution('4d6kh3') %}
<p>{{ (distribution.atLeast(15).toFloat * 100)|number_format(1) }} % to roll 15 or more.</p>

{{ tilttek_dice_probabilities('1d20+5', '2d10+4') }}
{{ tilttek_dice_probabilities('3d6', show: 'at_least') }}
```

The rounding is `floor`, `ceil`, `round`, `towards_zero` or `away_from_zero`. An invalid expression throws, as a roll
would.

The work of `tilttek_dice_fraction` is bounded, since a long table reduces three fractions a row. `digits` is taken
between 1 and 60. A term longer than twice the digits is not reduced and gives null, from its size alone; terms of up
to 18 digits are reduced on native integers; longer ones by `ext-gmp`, and give null without it. The percentage beside
it says the probability either way.

`tilttek_dice_probability_kit(component)` gives the name under which the application installed a component of the UX
kit, `ui:Card` for `Card` when its recipes are in `ui`. The templates call
`{% component (tilttek_dice_probability_kit('Card')) %}`, and an application overriding them does the same.

## The table and the chart

`@TilttekDiceProbability/probabilities.html.twig` shows, for an expression, in cards of the UX kit:

- a summary: the mean, the standard deviation and the range;
- when it is estimated, how many rolls it was drawn from, why, and its margin; when an explosion was cut at its depth,
  the share of rolls that would have exploded further;
- a bar chart of the probability of each value — or of at least it, or at most it —, as a percentage, drawn by the
  kit's `Chart` in SVG without script;
- a table of the kit, `Table`, of every value with its probability, at least and at most, each as a percentage and,
  when it is short enough, a fraction in lowest terms.

With a second expression, it first shows how often each is higher and how often both are equal, then both tables.

**The table is always there.** The chart, with the table of values the kit's `Chart` writes for screen readers, is
hidden from assistive technologies, `aria-hidden="true"`: the bundle's table, with its caption and its headers of
columns and rows, says the same and more to everyone, and stays when the styles do not load.

The look is that of the kit's recipes and of phil-ux. The classes, to style it further: `tilttek-dice-probabilities`,
`tilttek-dice-probabilities__versus`, `tilttek-dice-distribution`, `__summary`, `__estimate`, `__remaining`,
`__chart`, `__table`, and `tilttek-dice-probability` with `__fraction` for each probability.

## The component

```twig
<twig:TilttekDiceProbability:Distribution expression="4d6kh3" />
<twig:TilttekDiceProbability:Distribution expression="1d20+5" versus="2d10+4" show="at_least" locale="fr" rounding="ceil" />
```

A Live Component: its fields — the expression, a second one to compare it with, and what the chart shows — compute the
probabilities again as they are typed, without reloading the page. The fields are the kit's `Field`, each with its
label, and the choice of the chart is a `RadioGroup` in a set of fields with its legend; the text fields and the radio
buttons are written with the markup of the kit's `Input` and `RadioGroup:Item`, since a `data-model` given to a child
component would bind the child instead of the field. The results are announced politely to screen readers
(`aria-live="polite"`); an expression without probabilities says why in the kit's `Alert`: in the words of
`tilttek/dice` and `tilttek/dice-probability`, or, for a refusal of the bundle — groups too long to search, too many
values, the rate limit reached —, in its translated reason.

Each time the browser sends the expressions back, one computation is counted against the client's rate limit; the
[configuration](configuration.md#the-rate-limit-of-the-component) sets it, or turns it off.

| Property | Writable | What it holds |
|---|---|---|
| `expression` | yes | The expression; `2d6` by default |
| `versus` | yes | A second expression; none when empty |
| `show` | yes | `exactly`, `at_least` or `at_most`; an unknown value shows `exactly` |
| `locale` | no | The notation the expressions are written in |
| `rounding` | no | The rounding of divisions; the configured one when null or unknown |

The component writes no JavaScript of its own: Live Components bring theirs.
