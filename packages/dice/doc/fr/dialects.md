<!-- phil-dev source-sha256=6376732880932321a37e82f25af33d73c52ab7af49fa9a7af8aa6db73c99f2a9 -->
# Dialectes

Une table virtuelle lit certaines constructions autrement que la notation de la bibliothèque. Un `Dialect` écrit un lot
à sa façon, et signale ce qu'il ne sait pas écrire plutôt que de l'écrire mal.

```php
$translation = $dice->translate($dice->parse('4d6ro1, 6d10>=7'), 'roll20');
$translation->isComplete();   // true
$translation->text();         // "/r 4d6ro1\n/r 6d10>7"
```

Une `Translation` contient une expression par expression du lot, car une table lance chacune avec son propre total —
sauf pour DiceParser, qui les lance en une seule commande. `commands()` place la commande de lancer de la table avant
chacune, et `text()` les joint ligne par ligne. Quand une construction ne peut pas être écrite, `unsupported()`
l'énumère — dans la notation de la bibliothèque, avec la raison — et `commands()` et `text()` lèvent
`UntranslatableExpression`.

| Construction | Roll20 | Foundry VTT | Avrae |
|---|---|---|---|
| Commande | `/r ` | `/r ` | `!r ` |
| Relance unique, `ro1`, `ro<3` | `ro1`, `ro<2` | `r=1`, `r<3` | `ro1`, `ro<3` |
| Relance répétée, `r1`, `r<3` | `r1`, `r<2` | `rr=1`, `rr<3` | `rr1`, `rr<3` |
| Explosion, `!`, `!>=5` | `!`, `!>5` | `x`, `x>=5` | `e6`, `e>4` |
| Composée, pénétrante, `!!`, `!p` | `!!`, `!p` | signalée | signalée |
| Explosion unique, `!(1)` | signalée | `xo` | signalée |
| Plancher, plafond, `min3`, `max5` | signalés | `min3`, `max5` | `mi3`, `ma5` |
| Garder, écarter | `kh kl dh dl` | `kh kl dh dl` | `kh kl ph pl` |
| Réussites, `>=7` | `>7` | `cs>=7` | signalées |
| Échecs, `f1` | `f1` | `df=1` | signalés |
| Marques critiques, `cs>19`, `cf` | `cs>20`, `cf1` | signalées | signalées |
| Tri, `sa`, `sd` | `sa`, `sd` | signalé | signalé |
| Une liste de valeurs, `[10,25]kh1` | `{10,25}kh1` | `{10,25}kh1` | `(10, 25)kh1` |
| Dés Fudge | `dF` | `dF` | signalés |
| Division | `floor(a/b)` | `floor(a/b)` | `a//b` |
| `ceil()`, `round()` | `ceil(a/b)`, `round(a/b)` | `ceil(a/b)`, `round(a/b)` | l'argument seul, ses divisions signalées |
| `floor()` | `floor(x)` | `floor(x)` | l'argument seul |
| `abs()` | `abs(x)` | `abs(x)` | signalé |
| `min()`, `max()` | signalés | `min(a, b)`, `max(a, b)` | signalés |
| Reste, `a%b` | `a%b` | `a%b` | `a%b` |
| Puissance, `a^b` | `(a**b)` | `(a**b)` | signalée |

Les points de comparaison de Roll20 incluent leur seuil (`>5` vaut 5 ou plus) : une comparaison stricte s'écrit donc
un cran plus loin. Les sélecteurs d'Avrae excluent le leur. Une marque critique sans face s'écrit sur la face la plus
haute ou la plus basse.

L'arithmétique d'une table n'est pas toujours celle de la bibliothèque, et un dialecte n'écrit que ce qui donne le même
résultat :

- Une division s'écrit arrondie vers le bas, comme le fait un lancer par défaut. `translate($pool, $rounding)` prend
  l'arrondi du lancer : quand c'en est un autre, la division s'écrit quand même arrondie vers le bas, et
  `Translation::$notices` le signale — `1d6/2 is written rounded down; the roll rounds it PositiveInfinity.` —, la
  traduction restant complète. À l'intérieur de `ceil()` et de `round()`, une division s'écrit arrondie à leur façon.
- Roll20 et Foundry arrondissent les demis vers le haut, la bibliothèque en s'éloignant de zéro : une division à
  l'intérieur de `round()` ne s'écrit que lorsque aucun opérande ne peut être négatif, autant que l'expression le
  montre.
- Le `%` de Roll20 et de Foundry garde le signe du dividende, celui de la bibliothèque le signe du diviseur : un reste
  s'écrit tel quel quand aucun opérande ne peut être négatif, par un second reste — `(a%b+b)%b` — quand le diviseur est
  une constante, et il est signalé sinon. Le `%` d'Avrae a déjà le signe du diviseur.
- Une puissance s'écrit quand son exposant ne peut pas être négatif, car la table n'arrondit pas `2**-1`.

Toute autre construction de la bibliothèque — dés uniques répétés, dés tous identiques, transformations, filtres,
validateurs autres qu'une simple comparaison, portées, occurrences, groupes, jetons d'apparence, dés sur un intervalle,
dés Fudge pondérés, nombre ou faces lancés d'abord, dés précédents, limites d'explosions au-delà de ce qu'écrit une
table — est signalée par Roll20, Foundry VTT et Avrae, tout comme les constructions des
[instructions](instructions.md) : les références, les dés des instructions, les groupes, les listes de textes, les
phrases, les branches et `repeat()`. Les dialectes suivent la notation publique des dés de chaque table ; une table qui
change sa notation change son dialecte.

## Le DiceParser de Rolisteam

Le dialecte `diceparser` écrit la notation [lue](rolisteam.md) par `readRolisteam()`. DiceParser lance ensemble toutes
les instructions d'une commande : une traduction contient donc une seule expression, les instructions séparées par
`;`, après la commande `!`. Les références, les dés d'une instruction, les groupes, les listes de textes, les phrases,
les branches et `repeat()` sont écrits.

```php
$translation = $dice->translate($dice->parse('4d6dl1, $1+2, 3d10!!10(1)'), 'diceparser');
$translation->text();   // "!4d6k3;$1+2;3d10a[10]"
```

| Construction | DiceParser |
|---|---|
| Relance unique, répétée | `r[1]`, `R[<3]` |
| Explosion, limitée, sur la face la plus haute | `e[>8]`, `e(3)[10]`, `e[10]` |
| Explosion composée une seule fois | `a[10]` |
| Dés uniques, dés tous identiques | `u`, `t` |
| Transformations | `T[>5]{15}[<=2]{-1}` |
| Filtre, réussites | `f[*>1]`, `c[>=7]` |
| Garder | `k3`, `kl1` |
| Écarter, pour un nombre de dés connu | `4d6dl1` vaut `4d6k3` |
| Occurrences à partir d'une hauteur | `o3,7` |
| Groupes, jetons d'apparence, tris | `g10`, `p[2:accent]`, `s`, `sl` |
| Dés Fudge, dés sur un intervalle | `d[-1..1]`, `d[0..9]` |
| Portée d'une condition | `*` tous, `.` l'un d'eux, `:` le total |
| Division | `floor(a/b)`, `ceil(a/b)`, `round(a/b)` |
| Puissance | `(a^b)` |

Il signale des dés uniques relancés une seule fois, une explosion composée plus d'une fois, une explosion pénétrante,
une explosion de faces lancées d'abord sans sa face, les planchers et les plafonds, les échecs, les marques critiques,
une condition sur chaque dé tel qu'il est tombé, des occurrences jugées autrement qu'à partir d'une hauteur, des dés
écartés dont l'expression ne montre pas le nombre, les dés de plusieurs instructions, les dés Fudge pondérés, les dés
personnalisés, un reste qui peut être négatif, une puissance dont l'exposant peut être négatif, et `round()` d'une
division qui peut être négative. Ce qu'il écrit se relit en le même texte.

## Ajouter un dialecte

Étendez `Dialect` : donnez-lui un `name()` et sa `command()`, écrivez les `modifiers()` d'un ensemble de dés et la
`division()` arrondie comme le dit une fonction. Redéfinissez `fudge()`, `rangeDie()`, `valueList()`, `call()`,
`modulo()`, `power()`, `group()`, `reference()`, `instructionDice()`, `textList()`, `sentence()`, `conditional()` ou
`choice()` quand la table les écrit, et appelez `unsupported()` — ou `unwritten()` pour plusieurs modificateurs à la
fois — pour ce qu'elle ne sait pas écrire. Les fonctions utilitaires `plain()`, `plainSuccess()`, `bound()`,
`nonNegative()`, `function()` et `rounded()` couvrent ce que partagent les dialectes intégrés. Une table qui lance
ensemble toutes les instructions d'une commande renvoie son séparateur par `separator()` ; la traduction contient alors
une seule expression. Ajoutez-le à `Dialects`, où un dialecte du même nom remplace le dialecte intégré.
