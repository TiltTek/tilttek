<!-- phil-dev source-sha256=900a3655d8392e23adefc8d109163502772d119e55453973b0a9935bc2bd7c7c -->
# Distributions

## `Probabilities::distribution()`

```php
public function distribution(Node|Pool $expression, RoundingMode $rounding = RoundingMode::NegativeInfinity): Distribution
```

L'expression est un nœud de `tilttek/dice`, ou un groupe qui contient une seule expression : ce que donne
`DiceRoller::parse()`. Un groupe de plusieurs expressions est refusé, car chacune a sa propre distribution. L'arrondi
est celui d'un jet : il dit comment une division, ou une puissance à exposant négatif, est arrondie, sauf si `floor()`,
`ceil()` ou `round()` en décident autrement.

`Probabilities` reçoit les dés personnalisés que les expressions peuvent nommer, les limites qui les vérifient comme
pour un jet, et les [réglages](explosions-and-estimates.md) du calcul :

```php
new Probabilities(CustomDice $customDice = new CustomDice(), Limits $limits = new Limits(), Settings $settings = new Settings());
```

## `Distribution`

Chaque valeur que l'expression peut donner et son poids, c'est-à-dire le nombre de façons également probables de
l'obtenir. Le total est la somme des poids : `36` façons pour `2d6`, `1296` pour `4d6kh3`, `6^30` pour `30d6`. Chaque
probabilité est une [`Fraction`](exact-numbers.md) exacte sur ce total.

| Membre | Ce qu'il donne |
|---|---|
| `weights` | Le poids de chaque valeur, par valeur croissante, en nombres `Natural` |
| `total` | La somme des poids : les façons de lancer, ou les échantillons d'une estimation |
| `values()`, `minimum()`, `maximum()` | Les valeurs, la plus basse et la plus haute |
| `probability($value)` | La probabilité d'obtenir exactement cette valeur : `6/36` pour un 7 sur `2d6` |
| `atLeast($value)`, `atMost($value)` | La probabilité d'obtenir cette valeur ou plus, ou cette valeur ou moins |
| `matching($validator)` | La probabilité que la valeur respecte un validateur de la notation : `new Range(8, 10)` |
| `outcomes()` | Chaque valeur sous forme d'`Outcome` : sa probabilité, au moins et au plus ; les lignes d'un tableau |
| `mean()`, `variance()` | Des fractions exactes |
| `standardDeviation()` | La racine carrée de la variance, en nombre à virgule flottante |
| `remaining` | La probabilité qu'une explosion ait été coupée à sa profondeur ; voir [Explosions et estimations](explosions-and-estimates.md) |
| `estimate`, `isEstimated()` | Comment une distribution estimée a été tirée ; null quand elle est exacte |

## Ce qui est calculé exactement

| Construction | Comment |
|---|---|
| Constantes, `+ - * / % ^`, négation, `floor ceil round abs min max` | Chaque paire de résultats des côtés indépendants est combinée, avec l'arithmétique entière et l'arrondi d'un jet |
| `NdS`, `dF`, `dF.1`, `d[a..b]`, dés personnalisés dont les faces portent des valeurs | Un dé simple est additionné un dé à la fois par une fenêtre glissante ; les autres dés, par mise au carré de la convolution |
| Relances `r` et `ro` | Les faces du dé une fois relancé : les faces qui correspondent sont retirées, ou chacune est relancée une fois |
| Explosions `!`, `!!`, `!p`, `!!p`, avec ou sans limite | Suivies dé par dé jusqu'à leur limite, ou jusqu'à la profondeur des réglages |
| Planchers, plafonds, transformations, filtres jugés dé par dé | Chaque face devient ce qu'elle apporte |
| `kh kl dh dl` | Sur les multiensembles triés des dés : une valeur montrée par plusieurs dés est prise autant de fois d'un coup, pondérée par le nombre de façons de choisir ces dés, sans jamais énumérer chaque jet |
| Succès et échecs, `c[all:…]`, `c[any:…]`, `c[total:…]` | Ce que chaque dé apporte, additionné, puis jugé pour l'ensemble |
| Nombres de dés et de faces lancés d'abord, limites d'explosion lancées d'abord | Un mélange des distributions de chaque nombre, face ou limite |
| Listes de valeurs | Jugées une fois : elles ont une seule valeur |

Les dés uniques, les dés tous identiques qui explosent, les occurrences, les groupes, les filtres qui jugent l'ensemble,
les dés précédents (`@`), une sélection parmi des dés qui explosent en dés propres, les groupes d'expressions, `if` et
`switch`, et les dés de l'application dont la bibliothèque ne peut pas énumérer les faces sont plutôt estimés ; voir
[Explosions et estimations](explosions-and-estimates.md).

Un résultat qui ne peut pas être lancé rend la distribution impossible : une division par zéro, des dés qui ne peuvent
pas être lancés une fois leur nombre connu, un dé personnalisé inconnu. La bibliothèque lève alors `CannotCompute`, dont
l'exception précédente est le `CannotRoll` du jet. Les faces d'un dé personnalisé qui ne portent aucune valeur n'ont pas
de distribution non plus, pas plus qu'un texte, une liste ou `repeat()`, dont les résultats ne sont pas des nombres.

## Exemples

```php
$probabilities->distribution($dice->parse('2d20kh1'))->atLeast(15);              // 204/400
$probabilities->distribution($dice->parse('6d10>=7f1'))->mean()->reduced();       // 9/5
$probabilities->distribution($dice->parse('1d6/3'), RoundingMode::PositiveInfinity)->values(); // [1, 2]
$probabilities->distribution($dice->parse('3d6c[total:>=12]'))->probability(1);    // 81/216
```
