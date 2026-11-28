<!-- phil-dev source-sha256=5ad7674c43039c54e73e6550bfa9c9b56068a4bf345156259863b18f56af1de4 -->
# Exceptions

Toute exception de la bibliothèque implémente `Exception\InventoryException` : attrapez celle-ci pour les attraper
toutes.

| Exception | Lancée quand | Message |
|---|---|---|
| `InventoryRefused` | Les règles de l'inventaire refusent l'acte ; `refusal` dit pourquoi | Le message du refus, plus bas |
| `UnknownInventory` | Aucun inventaire ne contient la référence, ou aucun objet la clé | `No inventory is known as "…".`, `The inventory "…" holds no item under the key "…".` |
| `InvalidInventory` | Un nom, une clé, une raison, une quantité, un encombrement, un contenant, un lieu, des règles de transport, une capacité ou une limite hors limites ; une partie d'une pile déplacée sans nouvelle clé ; un transfert vers le même inventaire | Ce qui est hors limites, et la limite |

`InventoryRefused` étend `DomainException`, `UnknownInventory` `OutOfBoundsException`, `InvalidInventory`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyOpen` | An inventory already holds this reference. |
| `TooManyInventories` | This owner holds as many inventories as allowed. |
| `ItemExists` | The inventory already holds an item under this key. |
| `TooManyItems` | The inventory holds as many items as allowed. |
| `NotEnough` | The inventory holds fewer of this item than asked. |
| `NotAContainer` | This item is not a container. |
| `ContainerCycle` | A container cannot go inside itself or what it holds. |
| `TooDeep` | Containers would nest deeper than allowed. |
| `ContainerFull` | The container cannot hold this much. |
| `ContainerNotEmpty` | The container still holds items. |
| `SingleContainer` | A container is always a single item. |
| `NotCarried` | Only an item a carrier holds in hand or on them can be equipped. |
| `OverCapacity` | The carrier cannot carry this much. |

Une nouvelle valeur est vérifiée avant les limites et les clés déjà prises : ouvrir un inventaire ou ajouter un objet
avec un nom ou une clé invalide lance `InvalidInventory` même quand la référence ou la clé est déjà prise. Un contenant
que nomme la destination mais que l'inventaire ne contient pas lance `UnknownInventory`.
