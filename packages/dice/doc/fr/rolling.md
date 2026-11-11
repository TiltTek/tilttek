<!-- phil-dev source-sha256=2184f7b9497ff6931434ff4eb3b2b22d84b3c8f784a4ba6db279bbbf36008e7c -->
# Lancer

`Roller::roll(Pool, Randomizer, RoundingMode)` lance un lot avec le hasard qu'on lui donne :

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Rolling\Roller;

$result = new Roller()->roll(new DiceParser()->parse('4d6kh3'), new Randomizer(new Secure()));
```

Utilisez un moteur sûr en production. Un moteur avec germe — `Mt19937`, `Xoshiro256StarStar` — donne les mêmes dés
pour le même germe : c'est sur quoi reposent les tests et les
[lancers vérifiables et rejouables](verifiable-and-replayable.md).

Avant qu'un seul dé soit lancé, le lot est vérifié au regard des [limites](limits.md) et ses dés personnalisés sont
trouvés (`CannotRoll` nomme un dé inconnu). Les dés personnalisés dont les faces ne portent pas de valeur sont refusés
dans l'arithmétique — dans une opération, une fonction, un nombre ou des faces lancés d'abord, la limite d'une
explosion, un groupe, le nombre d'une liste ou de répétitions, le sujet d'une branche. Une branche ou `repeat()` donne
de tels dés tels quels, les libellés de leurs faces.

## Ce que garde un lancer

Un `PoolResult` contient un `ExpressionResult` par instruction, avec sa `output()` — un nombre, un texte ou une liste —
et son `total()` — null pour des dés personnalisés dont les faces ne portent pas de valeur, un texte, une liste, ou des
dés cédés à une fusion. Il contient aussi l'arbre des résultats qui l'a produit — `ConstantResult`, `OperationResult`,
`NegationResult`, `FunctionResult`, `DiceResult`, `TextResult`, `ListResult`, `BranchResult` et `ReferenceResult`
([instructions et résultats](instructions.md)) —, et l'arrondi (`rounding`) avec lequel il a été lancé.
`NodeResult::sets()` énumère les ensembles de dés d'un résultat, dans l'ordre où ils ont été lancés ou énumérés.

Un `DiceResult` est un ensemble de dés lancés, une liste de valeurs, les dés précédents ou ceux d'instructions jugés de
nouveau, ou les totaux d'un groupe. Il garde chaque `RolledDie`, dans l'ordre où ils sont tombés ou dans l'ordre qu'a
donné un tri :

| Propriété | Sens |
|---|---|
| `value` | La valeur finale du dé ; pour un dé personnalisé, la valeur de la face, ou 0 |
| `rolls` | Les jets dont le dé est fait : un seul, ou plusieurs pour un dé qui compose ses explosions ; `natural()` est leur somme, le dé tel qu'il est tombé |
| `face` | La `Face` d'un dé personnalisé |
| `origin` | `initial`, `reroll` ou `explosion` (`DieOrigin`) |
| `kept` | Si un filtre ou une sélection a gardé le dé |
| `rerolled` | Si une relance l'a remplacé — il ne compte plus |
| `exploded` | S'il a fait lancer un dé de plus |
| `success` | S'il a correspondu à la condition des réussites, quand l'expression les compte dé par dé |
| `failure` | S'il a retiré une réussite |
| `critical` | Sa marque critique, `critical-success` ou `critical-failure` (`Mark`), pour l'affichage |
| `look` | Son jeton d'apparence, pour l'affichage |

`counts()` indique si un dé compte — gardé et non relancé. `DiceResult::kept()` donne les dés qui comptent et
`tally()` compte les faces des dés personnalisés par identifiant. `DiceResult::groups` énumère les ensembles
d'occurrences ou les groupes qui atteignent une somme, par leurs valeurs. `DiceResult::parts` contient ce qui a été
lancé avant les dés — `count`, `sides`, `explosion-limit` —, ou les expressions d'un groupe. `DiceResult::notices`
indique ce que le lancer n'a pas pu faire exactement : `greedy-groups` (`Notice`) quand les groupes qui atteignent une
somme ont été trouvés de façon gloutonne.

`PoolResult::toArray()` écrit tout le lancer sous forme de tableaux simples, que garde un journal et que compare une
vérification ; `equals()` compare deux résultats.

## Relances, explosions et sélections

Une relance répétée relance un dé jusqu'à ce qu'il ne corresponde plus ; une relance unique ne relance pas le dé qui
le remplace, mais ce dé peut exploser. Un dé unique se compare aux dés de l'ensemble qui comptent, ceux lancés avant
lui. Les explosions se répètent jusqu'à ce qu'un dé n'en déclenche pas ou que sa limite soit atteinte ; une limite
lancée inférieure à 0 vaut 0. Les dés tous identiques se relancent, autant que l'ensemble en a lancé, tant que les dés
du dernier lot qui comptent montrent tous la même valeur.

Chaque dé que lance un opérateur répété est compté sur tout le lot. Les explosions de toute sorte, et les dés des dés
tous identiques, comptent au regard de la limite des explosions. Les relances, répétées, uniques ou de dés uniques,
comptent au regard de la limite des relances. Les dés dont le nombre est lancé, les valeurs des listes et les éléments
tirés des listes de textes comptent au regard de la limite des dés. Les répétitions de `repeat()` comptent au regard
de la limite des répétitions, et les textes des phrases au regard de la limite de leurs caractères.

Une sélection choisit parmi les dés qui comptent, les dés relancés étant exclus ; entre des dés égaux, le premier lancé
est choisi d'abord. Les dés précédents, `@`, sont les dés qui ont compté dans l'ensemble lancé ou énuméré juste avant
dans la même expression, chacun comme un nouveau dé avec sa valeur, sa face et ses jets ; `@` sans ensemble avant lui
dans l'expression lève `CannotRoll`.

Les groupes qui atteignent une somme sont le plus grand nombre que peuvent former les dés, jusqu'à 12 dés : chaque
groupe est tel qu'aucun dé ne peut le quitter sans que le groupe cesse d'atteindre la somme. Parmi les façons de former
le plus de groupes, la recherche garde la première qu'elle trouve, à partir du dé le plus haut. Au-delà de 12 dés, une
seule passe gloutonne les trouve, et le résultat le signale par l'avis `greedy-groups` : un dé qui atteint la somme à
lui seul forme un groupe ; sinon, le dé restant le plus haut commence un groupe, que ferme le dé le plus bas qui le
complète, ou que fait croître le dé restant le plus haut. Chaque groupe énumère ses dés à partir du plus haut, et les
groupes sont classés par leur dé le plus haut. Les occurrences énumèrent d'abord les ensembles les plus larges et, à
largeur égale, les plus hauts.

## Instructions et branches

Les instructions sont lancées dans l'ordre ; chacune voit les résultats de celles qui la précèdent, jamais leur `@`.
Les dés d'une instruction repris, liés ou fusionnés, sont de nouveaux dés avec la valeur, la face et les jets des dés
qui ont compté ; leurs marques critiques tombent sur la plus basse et la plus haute de leurs valeurs. Seule la branche
retenue est lancée ; les cas d'un aiguillage sont jugés dans l'ordre, et un aiguillage exclusif s'arrête au premier cas
vrai.

## Nombre et faces lancés d'abord

Un nombre ou des faces lancés d'abord le sont avant les dés, et leurs résultats sont gardés dans les parties. Les dés
sont ensuite vérifiés comme s'ils étaient écrits : un nombre inférieur à 1, des faces inférieures à 1, ou des
modificateurs que les dés ne peuvent pas prendre lèvent `CannotRoll` ; des faces au-delà de la limite lèvent
`LimitExceeded`.

## Arithmétique et arrondi

L'arithmétique porte sur des entiers. Une division arrondit comme le dit le `RoundingMode` passé au lancer —
`NegativeInfinity` par défaut, si bien que `-7/2` vaut `-4`. Un jeu arrondit vers le haut pour un camp et vers le bas
pour l'autre en passant le mode de celui qui lance. `floor()`, `ceil()` et `round()` indiquent comment arrondissent les
divisions à l'intérieur d'eux, quoi que dise le lancer ; `round()` arrondit les demis en s'éloignant de zéro. `abs()`,
`min()`, `max()` et `repeat()` gardent l'arrondi du lancer. Chaque mode du `RoundingMode` de PHP est pris en charge,
calculé sans virgule flottante.

Une puissance à exposant négatif vaut un divisé par la puissance, arrondi de la même façon : `2^-1` vaut 0 arrondi vers
le bas, 1 arrondi vers le haut. Un reste a le signe du diviseur. Une division ou un reste par zéro, zéro élevé à une
puissance négative, ou un résultat hors de la plage d'un entier lève `CannotRoll`.
