<!-- phil-dev source-sha256=69f930dc23d79acb919cb8693889a7a39d187baa1a8356bb1acc096fd5f98eaf -->
# Le journal et l'effacement

## Le journal

Chaque acte sur un inventaire écrit un `LogEntry` dans son journal, une fois que le dépôt a écrit le changement :

```php
foreach ($inventories->log($inventory, 20) as $entry) {
    $entry->change;     // Change::Moved
    $entry->item;       // 'arrows.bo'
    $entry->quantity;   // 10
    $entry->from;       // 'carrier:character:12'
    $entry->to;         // 'carrier:character:13'
    $entry->by;         // 'player:1', the acting reference, or null
    $entry->reason;     // 'Ada is down', or null
    $entry->at;
}
```

`log()` lit les entrées les plus récentes, de 1 à 1000, la plus récente d'abord ; pour des entrées d'un même moment, la
dernière écrite d'abord. Un inventaire conserve ses `Limits::$logPerInventory` entrées les plus récentes, les plus
anciennes écartées d'abord, et son journal disparaît à sa fermeture. Une raison compte de 1 à 200 caractères UTF-8, sans
caractère de contrôle ni espace en bordure.

| `Change` | Objet | Quantité | De | Vers |
|---|---|---|---|---|
| `Opened`, `Updated` | — | — | — | — |
| `Added`, `Increased` | L'objet | Unités ajoutées | — | Son lieu |
| `Decreased`, `Removed` | L'objet | Unités retirées | Son lieu | — |
| `Moved` | L'objet arrivé : la nouvelle clé pour une partie d'une pile | Unités déplacées | L'ancien lieu | Le nouveau lieu |
| `Equipped`, `Unequipped` | L'objet | — | — | — |
| `TransferredOut` | L'objet, dans la source | Unités envoyées | Son lieu | La référence de l'inventaire de destination |
| `TransferredIn` | L'objet arrivé, dans la destination | Unités reçues | La référence de l'inventaire source | Son lieu |
| `Forgotten` | L'objet que tenait son porteur effacé | Sa quantité | `carrier` | `stash` |

Les lieux sont écrits comme les donne `Place::toString()` et relus par `Place::fromString()`. `Closed` est communiqué aux
observateurs mais journalisé nulle part : le journal disparaît avec l'inventaire.

## Les événements

Un `Contract\InventoryObserver` reçoit un `InventoryChanged` après chaque acte, avec la référence de l'inventaire, le
`Change`, la clé de l'objet, la référence qui agit et le moment. Les noms, les quantités, les lieux et les raisons n'y
figurent jamais : un observateur qui relaie l'événement — une notification, un message sur un bus — lit l'objet ou le
journal quand il en a besoin.

## L'effacement

`forget()` efface une référence de tous les inventaires :

```php
$inventories->forget($ada);
```

Ce qu'elle portait directement, dans n'importe quel inventaire, passe à la réserve, non équipé, avec ce que contiennent
ses contenants, chaque passage journalisé comme `Forgotten`. Chaque entrée du journal qui la nomme la perd : la
référence qui agit est retirée et un lieu sur elle s'écrit `carrier`. Les inventaires dont elle est propriétaire
demeurent : l'application les ferme, à partir de `inventoriesOf()`, quand ils disparaissent avec elle.
