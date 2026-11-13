<!-- phil-dev source-sha256=4c40cc50316871343d65bcb6bfa84cfe6dc53889d10fe84d1fb97729e03775f9 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\BlazonWriter` | Un autre rédacteur pour l'anglais ou le français — un style maison, des termes plus anciens —, passé à `Blazonry` | [Le blason](blazon.md) |
| `Svg\Palette` | Les couleurs des émaux et des contours | [Le dessin](drawing.md#palette) |
| `Blazon\Blazonry` | Le blason que le moteur de rendu écrit dans la description de l'image | [Le dessin](drawing.md) |
| `CoatGenerator(furs:)` | Si les champs générés peuvent être de fourrure | [Générer des armes](generating.md) |

Aucun service ne garde d'état entre deux appels.
