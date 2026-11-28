<!-- phil-dev source-sha256=387922a060bd93e56d9b622aa606ed91c74fd48693e8c5511cc77d4d0ca4b32e -->
# Lancer

`Rolling\TableRoller` lance une table avec son dé par `tilttek/dice` et trouve la ligne où tombe le total. Il prend un
`DiceRoller` de `tilttek/dice` — les limites par défaut si on n'en donne pas — et un `Contract\TableObserver`
facultatif, et ne garde rien entre deux appels.

## Lancer une table

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\ReferenceTables\Rolling\TableRoller;

$roll = new TableRoller()->roll($catalogue->get('reaction'), new Randomizer(new Secure()));

$roll->natural;                         // what the die gave, 8
$roll->total;                           // the value looked up, 8
$roll->row->display('attitude', 'fr');  // "Incertain"
$roll->dice?->toArray();                // every die, as tilttek/dice keeps them
```

Le hasard est le `Random\Randomizer` que l'appelant passe : un moteur sécurisé en production, un moteur à germe fixe
dans les tests, le même germe donnant le même lancer. Une table sans dé lève `LookupRefused` ; il en va de même pour un
dé dont la première instruction ne donne aucun nombre — un texte, des dés personnalisés dont les faces ne portent
aucune valeur. Le dé se lance dans les limites du `DiceRoller`, qui lève sa propre `DiceException` au-delà.

`TableRoll` contient la clé de la table, la ligne et sa position dans les `$rows` de la table, la valeur du dé, le
modificateur, le total, si le total a été borné, et le `PoolResult` de `tilttek/dice` ; `isPhysical()` dit si le dé a
plutôt été lancé à la table.

## Modificateurs et borne aux extrémités

```php
$roll = $roller->roll($table, $randomizer, modifier: 2, clamp: true);
```

Le modificateur, de −1 000 000 à 1 000 000, s'ajoute au dé avant la consultation : un jet de réaction de 2d6 plus un
bonus. Beaucoup de tables disent qu'un total au-dessus de la dernière ligne lit la dernière ligne, et qu'un total sous
la première lit la première : avec `clamp`, un tel total prend la ligne de cette extrémité, et `TableRoll::$clamped` est
vrai. Un total qui tombe dans un trou entre deux lignes est refusé avec `UnknownRow` dans les deux cas, comme au-delà
des extrémités sans `clamp`.

## Dés lancés à la table

```php
$roll = $roller->enter($table, 9, modifier: 1);
```

Un joueur lance de vrais dés et le maître de jeu entre la valeur : `enter()` trouve la ligne de la même façon, avec le
même modificateur et la même borne, et donne un `TableRoll` sans `PoolResult`. Il sert aussi pour une table sans dé : un
niveau entré dans la table d'avancement. La valeur va de −1 000 000 à 1 000 000.

## Les dés d'une cellule

```php
$number = $roller->rollCell($roll->row, 'number', $randomizer)->totals()[0];
```

Une table de monstres errants donne, avec chaque rencontre, combien apparaissent : une cellule `dice`. `rollCell()` la
lance et donne le `PoolResult` de `tilttek/dice` ; une cellule qui ne contient aucun dé lève `LookupRefused`, une
cellule absente `UnknownTable`.

## L'observateur

Chaque lancer et chaque valeur entrée est annoncé, une fois sa ligne trouvée, au `Contract\TableObserver` que le
lanceur a reçu, sous la forme d'un `Event\TableRolled` :

| Champ | Contient |
|---|---|
| `table` | La clé de la table. |
| `position` | La position de la ligne dans les `$rows` de la table, à partir de 0. |
| `total` | La valeur consultée. |
| `physical` | Si le dé a été lancé à la table et entré. |

L'événement porte des identifiants et des nombres, jamais le texte de la ligne : une application en envoie un message
Messenger — un journal de la séance, une notification aux écrans des joueurs — et lit la ligne dans son catalogue. Un
lancer refusé n'annonce rien ; une cellule lancée n'est pas un événement.
