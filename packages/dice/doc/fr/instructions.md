<!-- phil-dev source-sha256=8caa0eaa3610ff226141836d5128d72555e4181592ce2b0b98eb166a192ad035 -->
# Instructions et résultats

Une entrée contient une instruction ou plus, séparées par `;` ou `,`. Les instructions sont lancées dans l'ordre,
chacune avec son propre résultat, et chacune peut parler de celles qui la précèdent — jamais de celles qui la suivent.
Un résultat est un nombre, un texte ou une liste.

```php
$result = $dice->roll($dice->parse('8d10; @1c[>=6]; @1c[=1]; "$2 successes, $3 ones"'), $randomizer);

$result->totals();    // [44, 5, 1, null], for instance
$result->outputs();   // [44, 5, 1, "5 successes, 1 ones"]
```

## Instructions et références

| Construction | Exemples | Sens |
|---|---|---|
| Instructions | `2d6; 1d8`, `2d6, 1d8` | Chacune lancée avec son propre résultat ; `;` et `,` les séparent de la même façon |
| Un résultat | `$1`, `$2+1` | Le résultat d'une instruction précédente, numérotée à partir de 1 : son total, son texte ou sa liste |
| Un élément | `$1[0]`, `$2[3]` | Un élément de ce résultat, numéroté à partir de 0 : de sa liste, ou des valeurs de ses dés qui comptent |
| Les dés d'une instruction | `@1`, `@1c[>=6]`, `@2kh1` | Les dés qui comptent dans une instruction précédente, jugés de nouveau comme un seul ensemble, sans être relancés |
| Dés liés | `b[1,2]kh2` | Les dés qui comptent dans plusieurs instructions, comme un seul ensemble ; les instructions gardent leurs propres résultats |
| Dés fusionnés | `m[1,2]kh1` | Le même ensemble, les instructions fusionnées cédant leurs dés |

`@` seul reste les dés de l'ensemble précédent, dans la même instruction ([notation](notation.md)) ; `@` suivi d'un
nombre représente les dés de cette instruction. Les dés des instructions acceptent les modificateurs qui jugent des
dés une fois lancés — garder et écarter, réussites, filtres, tris, marques critiques —, aucun qui en lance davantage.

Une instruction fusionnée n'a plus de total : `ExpressionResult::total()` est null et `mergedInto` donne le numéro de
l'instruction qui a pris ses dés. Son résultat est quand même gardé, et `$n` le lit toujours ; ses dés ne peuvent plus
être liés ni fusionnés. Une référence ne prend pas les dés de l'instruction qu'elle lit : `$1` est une valeur, `@1` un
ensemble de dés.

## Groupes

`{4d6, 2d10}kh1` lance chaque expression du groupe avec son propre total, puis juge les totaux comme des dés déjà
lancés : garder et écarter, réussites (`{3d6, 3d6, 3d6}>=10`), filtres, tris, marques critiques. Une expression d'un
groupe ne voit pas les dés extérieurs, et les dés précédents (`@`) après un groupe sont ses totaux. Les totaux sont
énumérés dans `DiceResult::parts` sous les noms `expression-1`, `expression-2` et ainsi de suite.

## Listes de textes

| Construction | Exemples | Sens |
|---|---|---|
| Une liste | `1L[sword,bow]`, `L[sword,bow]` | Tire un élément, ou autant que le dit le nombre ; le nombre est un nombre ou une expression entre parenthèses |
| Éléments uniques | `3Lu[a,b,c,d]` | Tire des éléments différents |
| Sans séparateur | `3Ln[ka,ro,mi]` | Joint les éléments tirés en un seul texte, `karomi` |
| Poids | `1L[a[2],b,c[3]]` | Un élément qui pèse 2 est tiré deux fois plus souvent qu'un élément qui pèse 1 |
| Intervalles | `1L[1..2:a,3..6:b]` | Lance un nombre de la borne la plus basse à la plus haute ; l'élément dont l'intervalle le contient est tiré |

Un élément s'écrit tel quel, jusqu'à la virgule, au crochet ou au poids qui le termine, ses espaces retirées ; entre
guillemets doubles, il peut contenir n'importe quel caractère, une virgule ou un crochet compris. Un élément est un
texte ou une clé de traduction, jamais du HTML. Les intervalles d'une liste se suivent, du premier au dernier, sans
trou ; une liste est pondérée ou sur des intervalles, pas les deux. Une liste unique tire parmi les éléments restants,
selon leurs poids ou la largeur de leurs intervalles.

`ListResult` garde les éléments tirés, l'indice de chacun dans la liste (`draws`), le nombre lancé pour chacun
(`rolls` : de 1 au poids total, ou sur les intervalles) et le nombre d'éléments quand il a été lancé. Chaque tirage
compte comme un dé au regard des [limites](limits.md).

## Textes et phrases

Un texte s'écrit entre guillemets doubles : `"Critical!"`, `"weapon.sword"`. Sans marqueurs, c'est un texte ou une clé
de traduction, tel que le lit l'application — jamais du HTML ; la page l'échappe. Avec des marqueurs, c'est une phrase,
dont les marqueurs sont remplacés par des résultats au moment du lancer :

| Marqueur | Remplacé par |
|---|---|
| `%1` | Le résultat dont il parle : le sujet de la branche dans laquelle il se trouve, ou l'instruction juste avant |
| `%2` | Les valeurs des dés qui comptent dans ce résultat, séparées par des virgules |
| `%3` | Les résultats de chaque instruction précédente, séparés par des virgules |
| `$n`, `$n[i]` | Le résultat de l'instruction `n`, ou son élément `i` |
| `@n` | Les valeurs des dés qui comptent dans l'instruction `n`, séparées par des virgules |

Une liste s'écrit avec ses éléments séparés par une virgule et une espace, ou accolés quand elle n'a pas de séparateur.
Une barre oblique inversée écrit un saut de ligne, `\n`, et écrit tel quel une barre oblique inversée, un guillemet
double, `%`, `$`, `@`, `[` ou un chiffre qui la suit : `"50\% off"` ; `"$1\2"` est le résultat de l'instruction 1 suivi
du chiffre 2. Un signe qui n'est pas suivi d'un chiffre s'écrit tel quel : `"100 %"`.

## Branches

| Construction | Exemples | Sens |
|---|---|---|
| Si | `1d20i[>=15]{"hit"}{"miss"}` | La première branche quand la condition est vraie, la seconde sinon — ou le sujet lui-même quand il n'y en a pas |
| Aiguillage | `1d6S[1..2]{"a"}[2..5]{"b"}{"c"}` | Sous forme de liste, la branche de chaque cas vrai ; la dernière branche, sans condition, quand aucun n'est vrai — ou le sujet lui-même |
| Aiguillage exclusif | `1d6S^[1..2]{"a"}[3..5]{"b"}{"c"}` | La branche du premier cas vrai |

Une branche s'applique à l'opérande juste avant elle — des dés, une liste de valeurs, un groupe, une référence, une
expression entre parenthèses : `(1d20+5)i[>=15]{…}`. Sa condition est un validateur entre crochets, avec la portée dans
laquelle elle juge le sujet : sa valeur par défaut, `all:` ou `any:` des dés qui y comptent, `total:` leur somme sans
ce qui leur est ajouté. Un sujet sans dés est jugé comme un seul dé de sa valeur. Une branche contient tout ce que peut
contenir une instruction : un nombre, des dés, un texte, une liste, une autre branche. Seule la branche retenue est
lancée. Le sujet est un nombre : un texte ou une liste ne peut pas être jugé.

`BranchResult` garde le sujet, les cas vrais — par leur indice à partir de 0, un `if` étant un seul cas —, si la
branche par défaut a donné le résultat, et le résultat.

## Répétitions

`repeat(4d6kh3, 6)` lance son premier argument autant de fois que le dit son second — un nombre, ou une expression
lancée d'abord — et donne les résultats dans une liste. Ce qu'il répète peut être un texte ou une liste, dont les
éléments rejoignent la liste. Chaque répétition compte au regard des [limites](limits.md), celles d'un `repeat()` dans
un `repeat()` une fois pour chaque passage.

## Commentaires

| Construction | Exemple |
|---|---|
| Jusqu'à la fin de la ligne | `2d6 # fire damage`, `2d6 // fire damage` |
| Entre crochets, après un opérande | `2d6[fire] + 1d4[cold]` |

Les commentaires ne prennent part ni au lancer ni à la forme canonique ; `Pool::$comments` les garde, selon l'indice à
partir de 0 de l'instruction dans laquelle ils sont écrits. Un commentaire jusqu'à la fin de la ligne se termine à un
saut de ligne : une instruction qui le suit commence donc à la ligne suivante. Un commentaire entre crochets ne contient
ni guillemet ni `#` ; après un modificateur qui prend un seuil entre crochets — une relance, une explosion, une marque
critique, des occurrences —, les crochets sont lus comme le seuil.

## Résultats

| Résultat | Donne |
|---|---|
| `ConstantResult`, `OperationResult`, `NegationResult`, `FunctionResult`, `DiceResult` | Un nombre ; les libellés des faces qui comptent pour les dés personnalisés dont les faces ne portent pas de valeur |
| `TextResult` | Un texte |
| `ListResult` | Une liste de nombres et de textes, ou un seul texte une fois joints |
| `BranchResult` | Ce qu'a donné sa branche |
| `ReferenceResult` | Ce qu'a donné l'instruction qu'il lit, ou son élément |

`NodeResult::output()` donne le nombre, le texte ou la liste ; `value()` donne le nombre, et lève `CannotRoll` pour un
texte ou une liste. `ExpressionResult::output()` est la sortie d'une instruction, `text()` la même écrite comme l'écrit
une phrase, `total()` le nombre quand c'en est un. `PoolResult::outputs()` énumère les sorties, `totals()` les totaux.

## La forme canonique

La forme canonique sépare les instructions par `", "`, écrit les dés d'une instruction `@n` et ceux de plusieurs
`b[…]` ou `m[…]`, les groupes `{a, b}`, une liste `nL` avec `u` puis `n` et ses éléments tels qu'écrits, sauf s'ils
exigent des guillemets, un aiguillage `S` — en majuscule, comme `L` et le `F` des dés Fudge, pour qu'aucun ne se lise
comme un tri ou un chiffre —, un `if` `i`, et un texte entre guillemets doubles avec les échappements qu'il exige. Les
commentaires ne sont pas écrits.

| Entrée | Forme canonique |
|---|---|
| `8d10; @1c[>=6]` | `8d10, @1>=6` |
| `1d6;1d8;B[1]` | `1d6, 1d8, @1` |
| `l[sword, bow]` | `1L[sword,bow]` |
| `2L[a[3], b, c[1]]` | `2L[a[3],b,c]` |
| `1L[a,"b, c"]` | `1L[a,"b, c"]` |
| `1d6s[1]{"a"}` | `1d6S[1]{"a"}` |
| `2d6 [fire] # roll` | `2d6` |
