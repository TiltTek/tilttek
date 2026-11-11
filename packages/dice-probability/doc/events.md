# Events

## `Probabilities::chance()`

```php
public function chance(Event $event, Node|Pool $dice): Chance
```

An event is judged on the faces of one set of dice — `4d6`, `3dF`, `5d{coin}` —, without their order. The probability
is exact, over the sorted multisets of the dice: 126 ways for four six-sided dice to fall without order rather than
1 296 rolls, each weighed by the number of rolls that give it. Rerolls are followed: the faces that count are those
left once rerolled. Other modifiers change the dice that count, and the probability is then
[estimated](explosions-and-estimates.md) from rolls, judged on the dice that count; so are too many multisets for the
work limit. An expression that is not one set of dice is refused with `CannotCompute`.

A `Chance` has its `probability`, an exact `Fraction`, its `estimate` and `margin()` when it is estimated, and its
`remaining`.

## The events

| Event | Matches when |
|---|---|
| `Count::atLeastOne($faces)` | At least one die shows the face, or a value that matches the validator |
| `Count::exactly($k, $faces)`, `Count::atLeast($k, $faces)`, `Count::atMost($k, $faces)` | That many dice show it |
| `new Count($validator, $comparator, $k)` | The number of dice that match, compared with `$k` |
| `OfAKind::double()`, `OfAKind::triple()`, `new OfAKind($n)` | At least that many dice show the same value, whatever it is |
| `new Straight()`, `new Straight($length)` | Consecutive values are shown: every die of the set, or a run of the length; one die alone is no straight |
| `new AllEqual()` | Every die shows the same value |
| `new AllDifferent()` | No two dice show the same value |
| `new AllMatch($validator)` | Every die matches: all above 3 with `new Comparison(Comparator::Greater, 3)` |
| `new AnyOf($event, …)`, `new AllOf($event, …)`, `new Not($event)` | Events composed |

The faces are given as an integer — `Count::atLeastOne(1)` — or as a validator of `tilttek/dice`: `Comparison`, `Range`,
`Modulo`, `Composite`.

## The reference example

"At least one 1, or a double of any value" — a double of 1 counts, since it shows a 1:

```php
$oneOrDouble = new AnyOf(Count::atLeastOne(1), OfAKind::double());

(string) $probabilities->chance($oneOrDouble, $dice->parse('2d6'))->probability; // "16/36"
(string) $probabilities->chance($oneOrDouble, $dice->parse('3d6'))->probability; // "156/216"
(string) $probabilities->chance($oneOrDouble, $dice->parse('4d6'))->probability; // "1176/1296"
```

## `Faces`

What an event judges: how many dice show each value. `Faces::of([3, 1, 3])` counts a list of values; `counts`,
`dice()`, `showing($value)`, `matching($validator)`, `mostOfAKind()` and `longestRun()` read it. An event of the
application implements `Event` with these — see [Extension points](extension-points.md).
