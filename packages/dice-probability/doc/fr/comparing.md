<!-- phil-dev source-sha256=5b1ded1845a54b4cbf6fd1445c87e28d3aabfec1d6265716f091ceecccc480e6 -->
# Comparer deux expressions

```php
public function compare(Node|Pool $first, Node|Pool $second, RoundingMode $rounding = RoundingMode::NegativeInfinity): Versus
```

Deux expressions lancées l'une contre l'autre, de façon indépendante : une attaque contre une défense, `1d20+5` contre
`2d10+4` :

```php
$versus = $probabilities->compare($dice->parse('1d20+5'), $dice->parse('2d10+4'));

(string) $versus->first;   // "1000/2000": the first is higher
(string) $versus->tie;     // "100/2000": both are equal
(string) $versus->second;  // "900/2000": the second is higher
```

Les trois probabilités sont des fractions exactes sur le produit des deux totaux, et leur somme vaut un. Un `Versus`
garde les deux distributions, `firstDistribution` et `secondDistribution`. Il est estimé dès que l'une des deux l'est :
`isEstimated()`, et `margin()` additionne les deux marges. `remaining()` est la probabilité qu'une explosion ait été
coupée à sa profondeur dans l'une ou l'autre.

Un `Versus` peut aussi être construit à partir de deux distributions déjà calculées : `new Versus($first, $second)`.
