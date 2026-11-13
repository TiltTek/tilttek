<!-- phil-dev source-sha256=f0124972bb61f54cc49afb11cdf50dbc3893f990c02a227a0ccdbd03ee6aef15 -->
# Exceptions

Toute exception de la bibliothèque implémente `Tilttek\Stickers\Exception\StickersException`. `StickerSheets` laisse
passer les exceptions de `tilttek/sets` : `UnknownSet` pour une planche jamais définie.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidSticker` | Un code, une référence d'image, une alternative textuelle, une licence, une attribution, une transformation, des limites, un détenteur, une surface ou un motif de modération est refusé, ou une requête de placement ne se lit pas | `The text alternative of a sticker is 1 to 250 characters of UTF-8, without control characters.` |
| `StickerRefused` | Un autocollant inconnu, retiré ou non possédé, une surface qui n'est pas celle du détenteur, une transformation hors des limites du site, une limite atteinte, ou un placement qui n'est pas celui du détenteur | `A surface holds at most 12 stickers on this site.` |
| `UnknownPlacement` | Un placement qui n'existe pas, ou n'existe plus | `There is no placement "4f1c…".` |

`InvalidSticker` étend `InvalidArgumentException` ; les autres étendent `RuntimeException`.
