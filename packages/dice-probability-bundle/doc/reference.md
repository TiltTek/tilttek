# Reference

| Type | Role |
|---|---|
| `TilttekDiceProbabilityBundle` | The bundle: its configuration and its services |
| `Application\DiceStatistics` | Distributions, comparisons and events of expressions written as text |
| `Application\Rounding` | The rounding modes by name: `floor`, `ceil`, `round`, `towards_zero`, `away_from_zero` |
| `Application\GroupSearch` | Weighs the search of the groups of an expression before it is estimated |
| `Application\Exception\Refusal` | A refusal of the bundle: the key and the parameters of its translated reason |
| `Application\Exception\GroupsTooCostly` | Groups whose search would pass `group_work_limit` |
| `Application\Exception\TooManyOutcomes` | A distribution of more values than `max_outcomes` |
| `Application\Exception\Throttled` | A computation the rate limit refused |
| `Infrastructure\DependencyInjection\ProbabilityServices` | Registers the services |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Names the components of the kit after the Toolkit's subdirectory, unless `kit_subdirectory` says it |
| `Infrastructure\Twig\KitComponents` | The names of the components of the UX kit, and the recipes the templates need |
| `Infrastructure\Twig\ProbabilityExtension` | The Twig functions and the filter |
| `Infrastructure\Twig\Components\DistributionComponent` | The Live Component `TilttekDiceProbability:Distribution` |
| `Infrastructure\RateLimiter\ComputationThrottle` | The rate limit of the component, per IPv4 address or IPv6 /64 |

| Service | Public |
|---|---|
| `Tilttek\DiceProbability\Settings` | no |
| `tilttek_dice_probability.limits`, the `Limits` of the probabilities | no |
| `Tilttek\DiceProbability\Probabilities` | yes |
| `Tilttek\DiceProbabilityBundle\Application\GroupSearch` | no |
| `Tilttek\DiceProbabilityBundle\Application\DiceStatistics` | yes |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents` | no, with Twig |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension` | yes, with Twig |
| `Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent` | no, with Symfony UX |
| `tilttek_dice_probability.limiter` and `tilttek_dice_probability.limiter.storage`, the sliding window and its storage | no, with the rate limit and no `limiter` |
| `Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle` | no, with Symfony UX and the rate limit |
