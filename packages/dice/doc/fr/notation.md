<!-- phil-dev source-sha256=2ba5da17d4548d2d94b21f390cacdcb1a42af4ed645e116d0a3d36d8ed807f51 -->
# Notation

Une expression est analysée, vérifiée et normalisée avant d'être lancée. Les lettres s'écrivent en minuscules ou en
majuscules. Une entrée contient une instruction ou plus ; les instructions, les références, les groupes, les listes de
textes, les textes, les branches et les commentaires sont décrits dans [instructions et résultats](instructions.md).

## Dés

| Construction | Exemples | Sens |
|---|---|---|
| Dés | `2d6`, `d20`, `d%` | Un nombre — 1 s'il est omis — et les faces ; `d%` vaut `d100` |
| Dés Fudge | `4dF`, `4dF.2`, `4dF.1` | Faces −1, 0 et +1 : deux de chaque, ou un −1, quatre faces vides et un +1 avec `.1` |
| Dés sur un intervalle | `4d[-1..1]`, `3d[0..9]` | Chaque entier d'une borne à l'autre, les deux comprises |
| Dés personnalisés | `3d{chromatic}` | Un dé que déclare l'application, nommé entre accolades ([dés personnalisés](custom-dice.md)) |
| Nombre et faces lancés d'abord | `(1d4+1)d6`, `3d(2*6)`, `(1d4)dF` | Une expression entre parenthèses donne le nombre, ou les faces d'un dé numéroté |
| Une liste de valeurs | `[10,25,43,8]kh1`, `[-1,3]` | Des entiers pris comme des dés déjà lancés, qui acceptent les modificateurs qui jugent des dés |
| Les dés précédents | `8d10>=6-@=1` | Les dés qui ont compté dans l'ensemble lancé ou énuméré juste avant, dans la même expression, jugés de nouveau |

Un nombre ou un nombre de faces entre parenthèses qui est une constante équivaut à la forme simple : `(3)d6` vaut
`3d6`. Un nombre lancé inférieur à 1, ou des faces lancées inférieures à 1, ne peuvent pas être lancés
([lancer](rolling.md)).

## Modificateurs

| Construction | Exemples | Sens |
|---|---|---|
| Relance | `4d6r1`, `4d6r<3`, `4d6ro1` | Relancer chaque dé tombé sur une face ou correspondant à un validateur, jusqu'à ce qu'il n'y corresponde plus ; `ro` relance une seule fois |
| Unique | `4d6u`, `4d6uo` | Relancer chaque dé qui montre une valeur déjà montrée par un dé précédent de l'ensemble, jusqu'à ce qu'elle diffère ; `uo` une seule fois |
| Explosions | `3d6!`, `3d6!5`, `3d6!>=5` | Lancer un dé de plus pour chaque dé tombé sur la face la plus haute, sur une face, ou correspondant à un validateur, et de nouveau pour chaque nouveau dé qui y correspond |
| Explosions composées | `3d10!!`, `3d10!!>8` | Les dés que lance une explosion s'ajoutent au dé qui a explosé, qui reste un seul dé |
| Explosions pénétrantes | `2d6!p`, `2d6!!p` | Chaque dé qu'ajoute une explosion compte un de moins ; un dé explose toujours sur la face telle qu'elle est tombée |
| Explosions limitées | `3d10!(3)`, `3d10!>8(1d4)` | Au plus tant d'explosions par dé, la limite écrite ou lancée d'abord |
| Relancer et ajouter | `3d10a10` | Une seule fois, lancer un dé de plus et l'ajouter au dé qui correspond : `3d10!!10(1)` |
| Dés tous identiques | `2d6t` | Quand chaque dé de l'ensemble montre la même valeur, relancer autant de dés et les ajouter, tant qu'ils sortent tous identiques |
| Plancher, plafond | `4d6min3`, `4d6max5`, `4dFmin-1` | Chaque dé compte au moins, ou au plus, cette valeur |
| Transformation | `5d10t[>5:15,<=5:0]` | Un dé qui correspond à la première condition prend sa valeur |
| Filtre | `4d6f[!=6]`, `4d6f[all:>1]` | Les dés qui ne correspondent pas sont écartés |
| Garder, écarter | `4d6kh3`, `4d6kl1`, `4d6dh1`, `4d6dl1` | Garder ou écarter les plus hauts ou les plus bas ; `k` seul garde les plus hauts, `d` seul écarte les plus bas |
| Avantage | `d20 adv`, `d20 dis` | `2d20kh1` et `2d20kl1`, pour un seul dé |
| Réussites | `6d10>=7`, `6d10=10`, `8d10c[6..10]` | Compter les dés qui correspondent, au lieu de les additionner |
| Échecs | `6d10>=7f1`, `6d10>=7f<3` | Chaque dé qui n'est pas une réussite et qui correspond retire une réussite |
| Occurrences | `10d10o`, `10d10o3[>=7]` | Les ensembles de dés de même valeur — au moins deux, ou la largeur écrite —, d'une hauteur qui correspond quand elle est donnée : la valeur est le nombre d'ensembles |
| Groupes | `5d10g10` | Des groupes de dés qui atteignent chacun la somme — le plus grand nombre que forment les dés, jusqu'à 12 dés ([lancer](rolling.md)) — : la valeur est le nombre de groupes |
| Marques critiques | `1d20cs`, `1d20cs>19`, `1d20cf1` | Marquer une réussite critique — la face la plus haute telle qu'elle est tombée — ou un échec critique — la plus basse —, pour l'affichage |
| Jetons d'apparence | `8d10look[2:accent,muted]` | Un jeton de l'apparence de la page pour les dés suivants, ou pour chaque dé restant quand aucun nombre n'est donné |
| Tri | `4d6s`, `4d6sa`, `4d6sd` | Énumérer les dés par valeur, croissante ou décroissante |

Une face seule, une comparaison écrite avec son signe, ou un validateur entre crochets indique à quels dés s'applique
une relance, une explosion ou une marque critique : `r1`, `r<3`, `r[1|2]`. Les comparaisons gardent le sens simple de
leur signe : `>5` est strictement supérieur à 5, `>=5` vaut 5 ou plus, `=6` vaut 6. Les échecs prennent une face ou une
comparaison ; `f[…]` est le filtre.

Un jeton d'apparence compte de 1 à 32 lettres minuscules, chiffres ou traits d'union, en commençant par une lettre : il
nomme un style que définit la page — `accent`, `muted` —, jamais une couleur.

## Les validateurs et leur portée

Entre crochets, un validateur indique quelles valeurs correspondent :

| Validateur | Exemple | Correspond à |
|---|---|---|
| Une valeur | `[6]`, `[-1]` | Cette valeur |
| Une comparaison | `[>=6]`, `[!=1]` | Les valeurs pour lesquelles la comparaison est vraie, `!=` compris |
| Un intervalle | `[8..10]`, `[-1..1]` | Les valeurs d'une borne à l'autre, les deux comprises |
| Un reste | `[%2=0]`, `[%3!=0]` | Les valeurs dont le reste par le diviseur vérifie la comparaison ; le reste a le signe du diviseur |
| Composites | `[>2&<6]`, `[1|6]`, `[%2=0^>4]` | Les deux (`&`), l'un ou l'autre (`|`), exactement un (`^`) ; `&` lie plus fort, les parenthèses regroupent |

Une condition — les réussites `c[…]`, le filtre `f[…]` et les transformations `t[…]` — peut écrire sa portée avant le
validateur, suivie d'un deux-points :

| Portée | Exemple | Vérifiée contre |
|---|---|---|
| Chaque valeur finale (par défaut) | `c[>=6]` | La valeur de chaque dé une fois son plancher, son plafond et sa transformation appliqués |
| Chaque dé | `c[die:6]` | Chaque dé tel qu'il est tombé, avant son plancher, son plafond ou sa transformation |
| Tous | `c[all:>2]` | Chaque dé qui compte : une réussite ou aucune ; gardés ou écartés ensemble |
| L'un d'eux | `c[any:6]` | Au moins un dé qui compte |
| Total | `c[total:>=10]` | La somme des dés qui comptent |

Une transformation juge chaque dé. Des réussites jugées ensemble ne comptent aucun échec.

## L'ordre des modificateurs

Quel que soit l'ordre dans lequel ils sont écrits, les modificateurs d'un ensemble de dés s'appliquent dans cet ordre.
Pendant le lancer de chaque dé : la relance, la relance d'une valeur déjà montrée, l'explosion. Une fois chaque dé
lancé : l'explosion des dés tous identiques. Ensuite, sur les dés lancés ou énumérés : le plancher et le plafond, les
transformations, le filtre, la sélection, les réussites et les échecs — ou les occurrences, ou les groupes —, les
marques critiques, les jetons d'apparence, et l'ordre dans lequel les dés sont énumérés.

Chaque modificateur peut être donné une seule fois. Un ensemble compte des réussites, des occurrences ou des groupes,
un seul des trois au plus ; les échecs exigent des réussites jugées dé par dé ; un plancher reste inférieur ou égal à
son plafond. Un lancer qui ne finirait jamais est refusé : une explosion que chaque face déclencherait, sauf si elle
est limitée (`d6!>=1`), ou une relance répétée de chaque face (`d6r<7`). Les dés uniques répétés exigent autant de faces
que de dés ; les dés tous identiques explosent à partir de deux dés. Une sélection garde d'un dé à tous, ou écarte d'un
dé à tous sauf un. Une liste de valeurs, les dés précédents, les dés des instructions et un groupe n'acceptent aucun
modificateur qui lance des dés ; les dés précédents et ceux des instructions gardent ou écartent ce qu'ils peuvent des
dés qu'ils trouvent.

## Arithmétique et fonctions

| Construction | Exemples | Sens |
|---|---|---|
| Opérations | `2d6+3`, `(1d8+2)*2`, `-1d4`, `10/3`, `10%3` | Des entiers ; `*`, `/` et `%` lient plus fort que `+` et `-` |
| Puissance | `2^10`, `2^-1`, `-2^2`, `2^3^2` | Lie plus fort qu'une négation — `-2^2` vaut −4 — et de droite à gauche |
| Arrondi | `floor(1d6/3)`, `ceil(1d6/3)`, `round(7/2)` | Les divisions à l'intérieur arrondissent vers le bas, vers le haut, ou au plus proche, les demis s'éloignant de zéro |
| Valeur absolue | `abs(1-1d6)` | |
| Plus petit, plus grand | `min(1d6, 3)`, `max(1d20, 1d20)` | De deux arguments ou plus |
| Répétitions | `repeat(4d6kh3, 6)`, `repeat(1d6, 1d4)` | Le premier argument lancé autant de fois que le dit le second, en une liste |
| Instructions | `2d6, 1d8`, `2d6; 1d8` | Plusieurs instructions lancées ensemble, chacune avec son propre résultat |

Une division arrondit comme le dit le lancer — vers le bas par défaut ([lancer](rolling.md)) —, sauf si `floor()`,
`ceil()` ou `round()` autour d'elle disent autre chose ; toute autre fonction garde l'arrondi du lancer. Une puissance
à exposant négatif vaut un divisé par la puissance, arrondi de la même façon. Un texte ou une liste ne prend aucune
part à l'arithmétique. Un reste a le signe du diviseur : `-7%3` vaut 2.

## La forme canonique

`Pool::canonical()` réécrit une expression en lettres minuscules — sauf le `F` des dés Fudge et le `L` et le `S` des
[listes de textes et des aiguillages](instructions.md) —, chaque nombre écrit, les modificateurs dans l'ordre où ils
s'appliquent, les instructions séparées par `", "`, et pas plus de parenthèses que n'en exige l'arbre. Un seuil sur une
face s'écrit avec la face seule (`r1`, `!6`), ou avec `=` quand elle est négative (`r=-1`). Des réussites jugées sur
chaque valeur finale par une comparaison l'écrivent seule (`>=7`), toute autre par `c[…]`. `s` s'écrit `sa`, `a10`
s'écrit `!!10(1)`, `o` écrit sa largeur.

| Entrée | Forme canonique |
|---|---|
| `4D6KH3` | `4d6kh3` |
| `d% + d4` | `1d100+1d4` |
| `d20 adv` | `2d20kh1` |
| `4d6kh3!r1` | `4d6r1!kh3` |
| `8d10c[>=6]-@c[=1]` | `8d10>=6-@=1` |
| `3d10a10` | `3d10!!10(1)` |
| `min(1d6,3)` | `min(1d6, 3)` |
| `((2))` | `2` |
| `3d{Chromatic}` | `3d{chromatic}` |

La forme canonique s'analyse de nouveau en le même arbre.

## Erreurs

Une entrée invalide lève `InvalidExpression`, dont `position` est le décalage de l'erreur, en caractères à partir de 0,
et dont le message la nomme : `Unexpected "x" at position 3 of "2d6x".` Les nombres valent 1 000 000 000 au plus ; les
parenthèses, les négations, les fonctions, les groupes, les branches et les parenthèses des validateurs s'imbriquent
sur 32 niveaux au plus. Une entrée qui s'analyse mais dépasse une limite lève `LimitExceeded` ([limites](limits.md)).
