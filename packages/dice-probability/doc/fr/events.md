<!-- phil-dev source-sha256=a3d582b570d291eab7138494fbd16c82dabe68e2170662054a1b0ce6edc29f72 -->
# Événements

## `Probabilities::chance()`

```php
public function chance(Event $event, Node|Pool $dice): Chance
```

Un événement est jugé sur les faces d'un seul ensemble de dés (`4d6`, `3dF`, `5d{coin}`), sans tenir compte de leur
ordre. La probabilité est exacte et porte sur les multiensembles triés des dés : quatre dés à six faces peuvent tomber
de 126 façons sans ordre, plutôt que 1 296 jets, et chacune est pondérée par le nombre de jets qui la donnent. Les
relances sont suivies : les faces qui comptent sont celles qui restent une fois les dés relancés. Les autres
modificateurs changent les dés qui comptent. La probabilité est alors [estimée](explosions-and-estimates.md) à partir de
jets, jugés sur les dés qui comptent. C'est aussi le cas quand les multiensembles sont trop nombreux pour la limite de
travail. Une expression qui n'est pas un seul ensemble de dés est refusée avec `CannotCompute`.

Une `Chance` a sa `probability`, une `Fraction` exacte, son `estimate` et sa `margin()` quand elle est estimée, et son
`remaining`.

## Les événements

| Événement | Correspond quand |
|---|---|
| `Count::atLeastOne($faces)` | Au moins un dé montre la face, ou une valeur qui respecte le validateur |
| `Count::exactly($k, $faces)`, `Count::atLeast($k, $faces)`, `Count::atMost($k, $faces)` | Ce nombre de dés la montre |
| `new Count($validator, $comparator, $k)` | Le nombre de dés qui correspondent, comparé à `$k` |
| `OfAKind::double()`, `OfAKind::triple()`, `new OfAKind($n)` | Au moins ce nombre de dés montre la même valeur, quelle qu'elle soit |
| `new Straight()`, `new Straight($length)` | Des valeurs consécutives sont montrées : par tous les dés de l'ensemble, ou sur une suite de cette longueur ; un dé seul ne forme pas une suite |
| `new AllEqual()` | Tous les dés montrent la même valeur |
| `new AllDifferent()` | Deux dés ne montrent jamais la même valeur |
| `new AllMatch($validator)` | Tous les dés correspondent : tous au-dessus de 3 avec `new Comparison(Comparator::Greater, 3)` |
| `new AnyOf($event, …)`, `new AllOf($event, …)`, `new Not($event)` | Des événements composés |

Les faces sont données par un entier (`Count::atLeastOne(1)`) ou par un validateur de `tilttek/dice` : `Comparison`,
`Range`, `Modulo`, `Composite`.

## L'exemple de référence

« Au moins un 1, ou un double de n'importe quelle valeur » : un double de 1 compte, puisqu'il montre un 1 :

```php
$oneOrDouble = new AnyOf(Count::atLeastOne(1), OfAKind::double());

(string) $probabilities->chance($oneOrDouble, $dice->parse('2d6'))->probability; // "16/36"
(string) $probabilities->chance($oneOrDouble, $dice->parse('3d6'))->probability; // "156/216"
(string) $probabilities->chance($oneOrDouble, $dice->parse('4d6'))->probability; // "1176/1296"
```

## `Faces`

Ce qu'un événement juge : combien de dés montrent chaque valeur. `Faces::of([3, 1, 3])` compte une liste de valeurs ;
`counts`, `dice()`, `showing($value)`, `matching($validator)`, `mostOfAKind()` et `longestRun()` la lisent. Un événement
de l'application implémente `Event` avec ces membres ; voir [Points d'extension](extension-points.md).
