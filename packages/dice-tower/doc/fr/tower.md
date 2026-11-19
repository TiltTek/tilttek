<!-- phil-dev source-sha256=af5454708da29c8dded2fc58a6cadfa4daabfa42fc06449b503dcba99998ff08 -->
# La tour en PHP

`DiceTower` est un service public. Une table, un lanceur et un membre sont des `Phil\Resource\ResourceReference` que
l'application fournit : `table:7`, `member:42`.

## Lancer et déposer

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Application\DiceTower;

$entry = $tower->roll(ResourceReference::of('table', '7'), '4d6kh3', ResourceReference::of('member', '42'), locale: 'fr');
```

`roll()` lit l'expression dans la notation de la locale, la lance dans le journal de `TilttekDiceBundle` — au nom du
lanceur, avec l'arrondi demandé — et la dépose dans la tour de la table. `drop()` dépose un jet que le journal garde
déjà, par son identifiant, et lève `UnknownRoll` pour un jet qu'il ne garde pas :

```php
$recorded = $diceRolls->rollReplayable('1d20+5', $member);
$entry = $tower->drop($table, $recorded->id(), $member);
```

Un jet déposé est gardé dans le journal avec une position après tout dépôt antérieur, poussé sur le sujet de la table,
et remis aux relais. Un hub ou un relais qui échoue est journalisé ; le jet reste dans le journal.

## Lire les entrées

```php
$tower->entries($table);       // the last entries, the earliest first
$tower->entries($table, 41);   // the entries after the position 41
$tower->entry($table, 42);     // one entry, or null
```

Les deux listes contiennent au plus autant d'entrées que `history`. Un jet effacé du journal depuis son dépôt est
laissé de côté. Les positions croissent d'une tour à l'autre, avec des trous entre les entrées d'une même table : un
spectateur retient la dernière position qu'il a vue et demande celles qui la suivent.

## La charge utile

`TowerEntry::toArray()` est ce qu'un abonné reçoit, en JSON :

```json
{
    "position": 42,
    "table": "table:7",
    "roll": "0d0000000e0000000f00000010000000",
    "roller": "member:42",
    "rolled_at": "2026-11-12T20:00:00+00:00",
    "results": [
        {
            "expression": "2d{coin}",
            "total": 1,
            "output": 1,
            "sets": [
                {"notation": "2d{coin}", "die": "coin", "dice": [
                    {"value": 1, "face": "heads", "kept": true},
                    {"value": 0, "face": "tails", "kept": true}
                ]}
            ]
        }
    ]
}
```

Elle ne porte que des identifiants et le résultat. Le lanceur est la référence opaque que l'application a donnée : la
page la transforme en nom pour les spectateurs autorisés à le voir, jamais le hub. Le résumé du résultat est lu avec
des bornes — pas plus de 16 niveaux de détail du journal, pas plus de 500 dés — et ignore ce qui n'a pas la forme du
journal.

## Vie privée

Quand une personne est effacée, effacez ses jets du journal et ses dépôts des tours :

```php
$diceRolls->eraseOwner($member);
$tower->eraseRoller($member);
```

Une entrée dont le jet a été effacé du journal n'est plus montrée, même avant que son dépôt soit effacé.

## Processus de longue durée

Rien n'est gardé entre deux requêtes : l'ordre d'une tour et les entrées qu'un spectateur a manquées viennent du
journal, lu depuis son stockage chaque fois. Le stockage en mémoire, pour les tests, est vidé entre deux requêtes d'un
*worker* par l'étiquette `kernel.reset`.
