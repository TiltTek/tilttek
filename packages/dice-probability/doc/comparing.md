# Comparing two expressions

```php
public function compare(Node|Pool $first, Node|Pool $second, RoundingMode $rounding = RoundingMode::NegativeInfinity): Versus
```

Two expressions rolled against each other, independently — an attack against a defence, `1d20+5` against `2d10+4`:

```php
$versus = $probabilities->compare($dice->parse('1d20+5'), $dice->parse('2d10+4'));

(string) $versus->first;   // "1000/2000": the first is higher
(string) $versus->tie;     // "100/2000": both are equal
(string) $versus->second;  // "900/2000": the second is higher
```

The three probabilities are exact fractions over the product of both totals, and add up to one. A `Versus` keeps both
distributions, `firstDistribution` and `secondDistribution`. It is estimated when either is: `isEstimated()`, and
`margin()` adds both margins. `remaining()` is the probability that an explosion was cut at its depth in either.

A `Versus` can also be built from two distributions already computed: `new Versus($first, $second)`.
