# Reference

| Type | Role |
|---|---|
| `Probabilities` | The entry point: `distribution()`, `compare()` and `chance()` |
| `Settings` | The explosion depth, the work limit, the samples and the seed of an estimate |
| `Distribution` | Every value of an expression and its weight; probabilities, at least, at most, mean, variance, standard deviation |
| `Outcome` | One value of a distribution with its probability, at least and at most |
| `Versus` | Two distributions against each other: the first higher, a tie, the second higher |
| `Chance` | The probability of an event |
| `Estimate` | How an estimated result was drawn, and its margin |
| `EstimateReason` | `TooLarge` or `Unsupported` |
| `Exact\Natural` | A natural number of any size |
| `Exact\Fraction` | An exact rational number |
| `Event\Event` | What the faces of a set show or not |
| `Event\Faces` | The faces of a set, counted by value |
| `Event\Count` | How many dice show a face, compared with a number |
| `Event\OfAKind` | A double, a triple, or more dice of a kind |
| `Event\Straight` | Consecutive values |
| `Event\AllEqual`, `Event\AllDifferent`, `Event\AllMatch` | Every die the same, all different, or all matching |
| `Event\AnyOf`, `Event\AllOf`, `Event\Not` | Events composed |
| `Exception\ProbabilityException` | The interface of the library's exceptions |
| `Exception\CannotCompute` | No distribution or probability can be given |

The classes of `Computation\` — the calculator, its weights, its budget, the multisets and the sampler — are internal.
