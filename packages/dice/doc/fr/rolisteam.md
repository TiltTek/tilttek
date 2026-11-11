<!-- phil-dev source-sha256=e978ccc453781ef95e6a387b8afdc1708d261bf400587444623e28a333e4d0ed -->
# Le DiceParser de Rolisteam

`RolisteamReader` lit une commande écrite dans la notation du DiceParser de Rolisteam — collée telle qu'elle a été
écrite, sans la commande de lancer — dans l'arbre de la bibliothèque : le même `Pool` que donne la notation propre à
la bibliothèque, lancé, écrit et traduit de la même façon.

```php
use Tilttek\Dice\DiceRoller;

$dice = new DiceRoller();
$pool = $dice->readRolisteam('5D10K3; 8d10c[>=6]-@c[=1]');
$dice->write($pool);   // "5d10!kh3, 8d10>=6-@=1"
```

Le lecteur réécrit les jetons de la commande dans la notation de la bibliothèque, puis les analyse : un jeton que les
deux notations écrivent de la même façon est gardé, un jeton que DiceParser écrit autrement est remplacé, et
l'analyseur fait le reste. Les textes, les noms des fonctions et des macros, et ce qui est écrit entre crochets —
valeurs, listes, éléments d'une liste de textes — sont gardés tels quels. Une erreur est située dans la commande telle
qu'elle a été écrite : `Unexpected "x" at position 5 of "2d6r[x]".` La commande respecte la longueur des
[limites](limits.md), tout comme le lot qu'elle donne, lancé dans le respect des autres limites.

## Ce qui est lu

| DiceParser | Exemple | La notation de la bibliothèque |
|---|---|---|
| Garder les plus hauts, les plus bas | `4d6k3`, `4d6kl1` | `4d6kh3`, `4d6kl1` |
| Garder et exploser | `5D10K3` | `5d10!kh3` |
| Relancer une seule fois, jusqu'à ce qu'il ne corresponde plus | `4d6r[1]`, `4d6R[<3]` | `4d6ro1`, `4d6r<3` |
| Explosion, limitée | `3d10e10`, `3d10e[>8]`, `3d10e(3)[10]` | `3d10!10`, `3d10!>8`, `3d10!10(3)` |
| Relancer et ajouter | `3d10a[10]` | `3d10a10` |
| Compter | `6d10c[>=7]`, `6d10c>7`, `6d10c10` | `6d10>=7`, `6d10>7`, `6d10=10` |
| Trier, décroissant et croissant | `10d6s`, `10d6sl` | `10d6sd`, `10d6sa` |
| Transformation, la valeur de tous les autres dés en dernier | `5d10T[>5]{15}[<2]{1}{0}` | `5d10t[>5:15,<2:1,<1|>0:0]` |
| Peindre | `8d10p[2:blue]` | `8d10look[2:blue]` |
| Occurrences d'une largeur et d'une hauteur | `10d10o2,7` | `10d10o2[>=7]` |
| Portée d'un validateur | `[*>2]`, `[.6]`, `[:>=30]`, `[?>5]` | `[all:>2]`, `[any:6]`, `[total:>=30]`, `[>5]` |

Les constructions que les deux notations écrivent de la même façon sont lues telles quelles : les dés `3D6`, les dés
sur un intervalle `1d[-1..1]`, les listes de valeurs `[10,25]k1`, les filtres `f[…]`, les dés uniques `u`, les dés
tous identiques `t`, les groupes `g`, les dés précédents `@`, les instructions séparées par `;`, `$1` et `$1[0]`, `@1`,
`m[…]` et `b[…]`, les listes de textes `L[…]`, les textes et leurs marqueurs, `i[…]{…}{…}`, `S[…]{…}` et `S^`, les
fonctions `floor`, `ceil`, `round`, `abs`, `min`, `max` et `repeat`, les opérateurs `+ - * / % ^`, les commentaires
`#`. Un jeton de peinture nomme un style de la page, comme le fait un [jeton d'apparence](notation.md#modificateurs) :
`blue` en est un, `#0000ff` n'en est pas un.

Les modificateurs d'un ensemble de dés s'appliquent dans l'ordre de la bibliothèque, quel que soit l'ordre dans lequel
ils sont écrits ; une division arrondit comme le dit le lancer ([lancer](rolling.md)), car la bibliothèque calcule en
entiers.

## Ce qui est refusé

| Construction | Message |
|---|---|
| La séparation des explosions, `y` | `The split of explosions "y" is not read` |
| Une fusion ou une liaison de toutes les instructions, `m` ou `b` sans crochets | `"m" is read with the instructions it takes: m[1,2]` |
| Tout ce que la notation de la bibliothèque n'analyse pas une fois réécrit | Le message de l'analyseur, à la position du jeton dans la commande |

## Macros

`read($input, $macros)` lit les macros que nomme une commande, `$name`, comme le fait l'analyseur : voir
[macros](macros.md). Le nom d'une macro n'est jamais réécrit : `$fire` reste la macro `fire`, son `r` et son `e`
intacts.

## Écrire dans la notation de DiceParser

Le dialecte `diceparser` réécrit un lot dans la notation de DiceParser, en signalant ce qu'il ne sait pas écrire : voir
[dialectes](dialects.md#le-diceparser-de-rolisteam).
