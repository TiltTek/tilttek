<!-- phil-dev source-sha256=0ffeca63dd441ba649611b58214dfd5ffcff227a2a975e8ece4b8cf4c8d87cdb -->
# Macros

`DiceMacros` garde des macros pour un site, une table et un membre, et lit les entrées qui les nomment : `$attack + 2`
prend l'arbre de la macro `attack` comme opérande. Un nom se lit comme un seul jeton, soit un `$` et les lettres et
chiffres collés à lui, jamais par remplacement de texte. Ainsi, une macro `a` laisse `aa` et `2d6a10` intacts, et une
macro garde ses propres parenthèses : `$attack*2` vaut `(1d20+5)*2`.

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\MacroShelves;
use Tilttek\DiceBundle\Domain\MacroLevel;

$shelves = new MacroShelves(
    site: ResourceReference::of('site', '1'),
    table: ResourceReference::of('table', '7'),
    member: ResourceReference::of('member', '3'),
);

$macros->define($shelves, MacroLevel::Table, 'attack', '1d20+5');
$macros->define($shelves, MacroLevel::Member, 'init', '1d20; $1+2');

$pool = $macros->parse('$attack*2', $shelves);        // (1d20+5)*2
$pool = $macros->parse('$init', $shelves);            // 1d20, $1+2
$pool = $macros->readRolisteam('$attack+1d6s', $shelves);
$dice->roll($pool);
```

## Rayons et niveaux

`MacroShelves` contient les propriétaires d'une entrée : un site, une table, un membre, des `ResourceReference` opaques
que donne l'application. Chacun peut être omis. Un nom est cherché d'abord sur le rayon du membre, puis sur celui de la
table, puis sur celui du site : l'`attack` du membre cache celle de la table, qui cache celle du site.

Les noms qu'écrit une macro sont cherchés à partir de son propre niveau : une macro d'un site ne nomme que les macros du
site, une macro d'une table celles de la table et du site. Une macro d'un site a le même sens à chaque table et pour
chaque membre.

## Définir, retirer, effacer

| Méthode | Effet |
|---|---|
| `define($shelves, $level, $name, $expression)` | Garde la macro pour le propriétaire que les rayons donnent à ce niveau, en remplaçant celle du même nom |
| `remove($level, $owner, $name)` | Retire une macro ; indique s'il y en avait une |
| `of($level, $owner)` | Les macros gardées à un niveau pour un propriétaire, par nom |
| `eraseOwner($owner)` | Efface les macros d'un propriétaire à tous les niveaux, quand le membre, la table ou le site est effacé |
| `parse($input, $shelves, $locale)` | Une entrée dans la notation d'une langue, ses macros résolues |
| `readRolisteam($input, $shelves)` | Une commande du DiceParser de Rolisteam, ses macros résolues |

Un nom est une lettre, puis des lettres et des chiffres, 32 au plus, gardé en minuscules. Une expression s'écrit dans
la notation de la bibliothèque. Elle n'est gardée que si elle s'analyse avec les macros de son niveau et de ceux qui
suivent : une macro qui nomme une macro absente, ou qui se nomme elle-même à travers d'autres, est refusée. Une macro
d'une seule instruction est un opérande partout où un opérande est permis ; une macro de plusieurs instructions est
l'entrée entière, écrite seule. Une macro retirée plus tard fait refuser les macros qui la nomment, avec le nom
manquant.

## Limites

Une entrée développe 32 macros au plus, les macros d'une macro comptées chaque fois qu'elle est développée : au-delà,
`LimitExceeded`. La réserve que donne une entrée est vérifiée selon les limites de la bibliothèque une fois ses macros
incluses, comme toute réserve. Chaque macro est lue une fois par entrée, et rien n'est gardé d'une entrée à l'autre :
`DiceMacros` ne garde aucun état d'une requête à l'autre.

## La table

Les macros sont gardées dans la table `tilttek_dice_macro`, ou en mémoire avec `store: memory` ; voyez
l'[installation](installation.md#la-table-des-macros).
