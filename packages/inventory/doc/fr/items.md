<!-- phil-dev source-sha256=560a5f402c05ac48d3d244c0c23d8e214454f2b2f2e60fdebb123b5ae794ce11 -->
# Objets et lieux

## Inventaires

Un inventaire contient ce qu'un personnage ou un groupe possède pendant le jeu. L'application l'ouvre sous une
référence qu'elle donne, pour un propriétaire, avec un nom de 1 à 100 caractères et la clé du système de jeu dont les
règles de transport le pèsent, ou aucune :

```php
$inventories->open($inventory, $party, 'The party\'s gear', 'srd-5.2', $by);
$inventories->rename($inventory, 'The wagon', $by);
$inventories->useSystem($inventory, 'knave', $by);   // or null: never weighed
$inventories->inventoriesOf($party);                // the earliest opened first
$inventories->close($inventory, $by);               // with its items and its log
```

Une référence contient un inventaire (`Refusal::AlreadyOpen`) ; un propriétaire en contient au plus
`Limits::$inventoriesPerOwner` (`Refusal::TooManyInventories`). Un personnage garde son propre inventaire et son groupe
en a un autre : un objet passe de l'un à l'autre par un transfert.

## Les objets et leur encombrement

Un objet vit sous une clé de l'application — de 1 à 96 lettres minuscules, chiffres, `_`, `-`, ou points simples entre
eux —, avec un nom de 1 à 100 caractères, une quantité de 1 à 1 000 000 et un `Burden` :

```php
use Tilttek\Inventory\Burden;

new Burden(10);          // 10 units of weight each, no slot
new Burden(10, 2);       // and 2 slots for each one: a two-handed sword
new Burden(1, 1, 20);    // 1 slot for each stack of 20: arrows
Burden::none();          // weighs nothing, takes no slot
```

Le poids est celui d'une unité, de 0 à 100 000, dans l'unité entière que choisit le système : des livres, des dixièmes
de livre pour un système qui pèse les pièces, des dixièmes de Bulk quand ses objets légers pèsent un dixième. Les
emplacements, de 0 à 1000, sont ceux d'une pile de `perSlot` unités, de 1 à 10 000 ; une pile entamée prend tous ses
emplacements, de sorte que 21 flèches en prennent 2. Les deux mesures sont conservées sur chaque objet : les règles de
transport choisissent celle qui compte.

```php
$inventories->add($inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::carriedBy($ada), by: $gm, reason: 'bought');
$inventories->increase($inventory, 'arrows', 5, $by, 'found');      // the item where it is
$inventories->decrease($inventory, 'arrows', 3, $by, 'shot');       // null once none is left
$inventories->remove($inventory, 'rope', $by, 'burnt');             // the whole entry
```

Une clé contient un objet (`Refusal::ItemExists`) ; un inventaire contient au plus `Limits::$itemsPerInventory` objets
(`Refusal::TooManyItems`) ; diminuer de plus que la quantité est refusé avec `Refusal::NotEnough`. Une quantité donnée
va de 1 à 1 000 000, et un objet n'en contient jamais davantage. `items()` lit les objets, le plus ancien ajouté
d'abord, les égalités départagées par clé, et `itemsAt()` ceux qui sont directement à un lieu.

## Les lieux

`Place` dit où est un objet :

| Lieu | Écrit | Ce que c'est |
|---|---|---|
| `Place::stash()` | `stash` | Porté par personne : le campement, la charrette, la banque |
| `Place::carriedBy($ada)` | `carrier:character:12` | Sur un porteur, sous la référence que donne l'application |
| `Place::inside('pack')` | `container:pack` | Dans le contenant de cette clé, dans le même inventaire |

Un objet ajouté sans lieu va dans la réserve. La bibliothèque ne vérifie jamais un porteur par rapport à un groupe ou à
une fiche : l'application donne les références qui peuvent porter. Un objet dans un contenant est porté par celui qui
porte le contenant, à n'importe quelle profondeur.

## Les contenants

Un objet est un contenant quand il est ajouté avec un `Container` : le maximum qu'il contient en poids et en
emplacements, chacun de 1 à 1 000 000 000 ou aucun pour aucune limite, et si ce qu'il contient pèse sur son porteur.

```php
use Tilttek\Inventory\Container;

$inventories->add($inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($ada), new Container(maxWeight: 30));
$inventories->add($inventory, 'bag', 'Bag of holding', 1, new Burden(15), Place::inside('pack'), new Container(500, null, false));
$inventories->add($inventory, 'anvil', 'Anvil', 1, new Burden(100), Place::inside('bag'));
$inventories->fill($inventory, 'pack');   // weight 15: the bag, not the anvil
```

Un contenant est toujours un objet unique (`Refusal::SingleContainer` sur `increase()`) ; il contient ce que ses limites
permettent, quoi que compte le système (`Refusal::ContainerFull`), ce que contient un contenant à l'intérieur comptant
pour le contenant extérieur, sauf si son contenu ne pèse rien ; il se place dans un autre contenant à une profondeur
d'au plus `Limits::$containerDepth` (`Refusal::TooDeep`), jamais dans lui-même ni dans ce qu'il contient
(`Refusal::ContainerCycle`) ; il ne se déplace qu'une fois vide (`Refusal::ContainerNotEmpty`). Placer un objet dans un
objet qui n'est pas un contenant est refusé avec `Refusal::NotAContainer`.

## Le déplacement

`move()` place un objet ailleurs dans l'inventaire, un contenant avec tout ce qu'il contient :

```php
$inventories->move($inventory, 'pack', Place::carriedBy($bo), by: $gm, reason: 'Ada is down');
$inventories->move($inventory, 'arrows', Place::carriedBy($bo), quantity: 10, as: 'arrows.bo');
```

Une partie d'une pile se déplace sous une nouvelle clé, `$as`, ajoutée au moment du déplacement ; le reste demeure sous
l'ancienne clé. Une partie sans nouvelle clé, ou une nouvelle clé pour la pile entière, est refusée avec
`InvalidInventory` ; plus que la quantité, avec `Refusal::NotEnough`. La destination est vérifiée comme pour un objet
ajouté : les contenants où il se trouve, sa profondeur, et la charge de son porteur quand les règles refusent de
dépasser la capacité.

## L'équipement

Un porteur équipe ce qu'il tient directement — l'épée qu'il manie, l'armure qu'il porte, le sac à dos sur son dos :

```php
$inventories->equip($inventory, 'sword', $by);
$inventories->unequip($inventory, 'sword', $by);
```

Seul un objet sur un porteur est équipé (`Refusal::NotCarried` pour un objet dans la réserve ou dans un contenant). Un
objet qui se déplace, ou qui passe à un autre inventaire, n'est plus équipé ; équiper un objet équipé ne change rien et
ne journalise rien. La bibliothèque enregistre l'état ; ce que fait un objet équipé — une armure, un bonus, une
condition — relève de l'application.

## Le transfert

`transfer()` amène un objet, ou une partie d'une pile, à un autre inventaire, sous sa clé ou `$as`, dans la réserve
quand aucun lieu n'est donné :

```php
$inventories->transfer($partyInventory, 'gold-ring', $adaInventory, Place::carriedBy($ada), 1, 'ring', $gm, 'her share');
```

La destination est vérifiée comme pour un objet ajouté ; un contenant ne passe qu'une fois vide
(`Refusal::ContainerNotEmpty`) ; un transfert vers le même inventaire est refusé avec `InvalidInventory`. Les deux
journaux l'enregistrent : `TransferredOut` dans la source, `TransferredIn` dans la destination. Une application qui sert
des requêtes concurrentes verrouille les deux inventaires.
