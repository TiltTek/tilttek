<!-- phil-dev source-sha256=b5c05364feefb2088e5269449b6ccb3e462b4df54930bd74ad32d1cf3c516c12 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Où |
|---|---|---|
| `Contract\StickerCatalog` | Les autocollants du site ; `Store\InMemoryStickerCatalog` les prend tous d'un bloc | [Le catalogue](catalogue.md#le-port-du-catalogue) |
| `Contract\OwnershipStore` | Les autocollants que possède chaque détenteur ; `Store\InMemoryOwnershipStore` les garde en mémoire | [Le catalogue](catalogue.md#accorder-et-reprendre) |
| `Contract\PlacementStore` | Les placements ; `Store\InMemoryPlacementStore` les garde en mémoire | [Placer des autocollants](placing-stickers.md#concurrence) |
| `Contract\SurfacePolicy` | Les surfaces que chaque détenteur peut utiliser ; `Policy\InMemorySurfacePolicy` les assigne | [Placer des autocollants](placing-stickers.md#surfaces) |
| `Contract\PlacementModerator` | La modération des nouveaux placements | [Montrer et modérer](showing-and-moderating.md#modération) |
| `Limits` | Les limites du site | [Placer des autocollants](placing-stickers.md#les-limites-dun-site) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui est informé de chaque changement | [Montrer et modérer](showing-and-moderating.md#événements) |
| `Psr\Clock\ClockInterface` | Le moment des placements, gardé en UTC | [Installation](installation.md#câbler-la-bibliothèque) |
| `Tilttek\Sets\Sets` | Les ensembles que sont les planches d'autocollants | [Planches et albums](sheets.md) |

Chaque point est passé à un constructeur ; `Stickers` et `StickerSheets` ne gardent aucun état entre deux appels.
