<!-- phil-dev source-sha256=0978ad4406a4a278dcba1ba70ac26e368f960ea2f63c9f5ead3172a66157bfe9 -->
# Référence

| Type | Rôle |
|---|---|
| `Stickers` | Accorde et reprend, place, déplace et supprime, masque et rétablit, montre une surface et efface |
| `StickerSheets` | Planches et albums d'autocollants comme ensembles de `tilttek/sets`, mesurés par rapport aux autocollants possédés |
| `Sticker` | Un autocollant du catalogue : référence d'image, alternative textuelle, licence, attribution, retiré |
| `Placement` | Un autocollant placé par un détenteur sur une surface, avec sa transformation, son moment en UTC et sa modération |
| `PlacementRequest` | Ce qu'un détenteur demande de placer, lu depuis un formulaire ou du JSON |
| `Transform` | La position, l'échelle et la rotation d'un placement, dans des bornes absolues |
| `Limits` | Les limites d'un site : par surface, par détenteur, échelles et rotation |
| `Verdict` | Ce qu'un modérateur dit d'un nouveau placement : montré, ou masqué pour un motif |
| `ViewerPreferences` | Si un spectateur voit les autocollants |
| `PlacementView` | Un placement avec son autocollant, son alternative textuelle et son crédit |
| `Contract\StickerCatalog` | Les autocollants du site |
| `Contract\OwnershipStore` | Les autocollants que possède chaque détenteur |
| `Contract\PlacementStore` | Les placements |
| `Contract\SurfacePolicy` | Les surfaces que chaque détenteur peut utiliser |
| `Contract\PlacementModerator` | La modération des nouveaux placements |
| `Store\InMemoryStickerCatalog`, `Store\InMemoryOwnershipStore`, `Store\InMemoryPlacementStore` | Les adaptateurs en mémoire |
| `Policy\InMemorySurfacePolicy` | Des surfaces assignées à leurs détenteurs, en mémoire |
| `Event\StickerGranted`, `StickerRevoked`, `StickerPlaced`, `PlacementHidden`, `PlacementRestored`, `PlacementRemoved` | Voir [Montrer et modérer](showing-and-moderating.md#événements) |
| `Exception\StickersException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidSticker`, `StickerRefused`, `UnknownPlacement` | Voir [Exceptions](exceptions.md) |

`Guard` est interne.
