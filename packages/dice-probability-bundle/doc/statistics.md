# Statistics in PHP

`Tilttek\DiceProbabilityBundle\Application\DiceStatistics` takes expressions as text and reads them with the
`DiceRoller` of `TilttekDiceBundle`:

| Method | What it gives |
|---|---|
| `distribution($expression, $locale = null, $rounding = null)` | The `Distribution` of one expression |
| `compare($first, $second, $locale = null, $rounding = null)` | The `Versus` of two expressions |
| `chance($event, $dice, $locale = null)` | The `Chance` of an event on a set of dice |
| `canonical($expression, $locale = null)` | The canonical form, or the expression as written when it cannot be read |

The locale names the notation the expression is written in — `'de'` reads `2W6` —; the rounding is a `Rounding`, the
configured one when null. An invalid expression throws the `InvalidExpression` of `tilttek/dice`, a limit exceeded its
`LimitExceeded` — `max_dice` included —, an expression without distribution the `CannotCompute` of
`tilttek/dice-probability`, groups too long to search the bundle's `Application\Exception\GroupsTooCostly`, before
the library rolls anything, and a distribution of more values than `max_outcomes` the bundle's
`Application\Exception\TooManyOutcomes`. Both implement `Application\Exception\Refusal`: besides their message, the key
and the parameters of their reason in the `tilttek_dice_probability` translation domain.

```php
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;

final readonly class Odds
{
    public function __construct(private DiceStatistics $statistics)
    {
    }

    public function oneOrDouble(int $dice): string
    {
        return (string) $this->statistics->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), $dice.'d6')->probability;
    }
}
```

`Tilttek\DiceProbability\Probabilities` is a service too, on the same custom dice and limits, for expressions already
parsed. It does not weigh the search of groups: an application that gives it expressions typed by anyone checks them
with `Application\GroupSearch` first, as `DiceStatistics` does.

`GroupSearch` weighs the groups of a parsed expression: `work($expressions)` gives the steps of one sample, and
`check($pool, $expression)` throws `GroupsTooCostly` when those steps, times the samples, pass its work limit. The
[configuration](configuration.md#the-search-of-groups) explains how a set is weighed.

`Rounding` names the rounding modes of the configuration and the templates: `Floor`, `Ceil`, `Round`,
`TowardsZero`, `AwayFromZero`; `mode()` gives PHP's `RoundingMode`.
