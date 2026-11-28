<!-- phil-dev source-sha256=2be9e050665658fc7de13b14c2c7127c5755cc439e227f50b7a41184ec56d7a5 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Valeur par défaut |
|---|---|---|
| `Contract\InventoryStore` | Où sont conservés les inventaires, les objets et les journaux | `Store\InMemoryInventoryStore` dans les tests |
| `Contract\RuleBook` | Les règles de transport de chaque système de jeu | `Rules\StaticRuleBook`, vide : rien n'est pesé |
| `Contract\CarrierCapacity` | La capacité de chaque porteur | `Rules\DefaultCapacity` : la valeur par défaut des règles |
| `Contract\InventoryObserver` | Ce qu'il faut faire d'un changement d'inventaire | Aucune |
| `Limits` | Ses limites d'inventaires par propriétaire, d'objets par inventaire, de profondeur des contenants et d'entrées du journal | 20, 1000, 4 et 1000 |
| `Psr\Clock\ClockInterface` | Le moment de chaque acte | — |

## Écrire un dépôt d'inventaires

Un `InventoryStore` nomme les inventaires, les propriétaires et les porteurs par leurs références écrites
`kind:identifier`, et les objets par leurs clés dans leur inventaire. Il conserve :

- les inventaires, par référence — `inventory()`, `saveInventory()`, `removeInventory()`, qui retire avec l'inventaire
  ses objets et son journal, et `inventoriesOf($owner)`, le plus ancien ouvert d'abord, les égalités départagées par
  référence ;
- les objets, un par inventaire et par clé — `item()`, `saveItem()`, qui remplace l'objet de même clé, `removeItem()`,
  `countItems()`, `items()`, le plus ancien ajouté d'abord, les égalités départagées par clé, et `carriedBy($carrier)`,
  les objets placés directement sur un porteur dans tous les inventaires, inventaire après inventaire dans l'ordre de
  leurs références ;
- les journaux — `appendLog($entry, $keep)`, qui ajoute l'entrée puis écarte les plus anciennes au-delà de `$keep`,
  `log($inventory, $limit)`, la plus récente d'abord, la dernière écrite d'abord pour un même moment, et
  `eraseFromLog($reference)`, qui réécrit chaque entrée qui nomme la référence comme le donne `LogEntry::without()`.

Le lieu d'un objet est conservé comme l'écrit `Place::toString()` et relu par `Place::fromString()` ; son encombrement,
sous forme de trois nombres entiers ; son contenant, sous forme de deux nombres entiers nullables et d'un indicateur.
La bibliothèque vérifie les limites d'un inventaire, les quantités, les contenants et les charges avant d'écrire : une
application qui sert des requêtes concurrentes exécute chaque acte de `Inventories` dans une transaction, ou sous un
verrou sur l'inventaire — sur les deux pour un transfert —, afin que deux requêtes ne prennent jamais les mêmes flèches.

## Livres de règles et capacités

Un `RuleBook` donne les `CarryRules` d'une clé de système, ou null quand il n'en déclare aucune ; un `CarrierCapacity`
donne la capacité d'un porteur dans un inventaire, selon la mesure des règles, de 0 à 1 000 000 000 000. Les deux lisent
ce dont ils ont besoin — les systèmes, les variantes avec lesquelles joue une table, la fiche du personnage — et ne
gardent aucun état entre deux requêtes, à part ce qu'ils lisent. [Règles de transport et charges](carrying.md) en
montre un de chaque.

## Observateurs

Un `InventoryObserver` reçoit un `InventoryChanged` après chaque acte, une fois que le dépôt a écrit, avec des
identifiants seulement — l'inventaire, la clé de l'objet, la référence qui agit — le `Change` et le moment. Il ne garde
aucun état entre deux appels. [Le journal et l'effacement](log.md) liste les changements.
