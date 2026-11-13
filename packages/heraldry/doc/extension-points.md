# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\BlazonWriter` | Another writer for English or French — a house style, older terms —, passed to `Blazonry` | [Blazon](blazon.md) |
| `Svg\Palette` | The colours of the tinctures and of the outlines | [Drawing](drawing.md#palette) |
| `Blazon\Blazonry` | The blazon the renderer writes in the image's description | [Drawing](drawing.md) |
| `CoatGenerator(furs:)` | Whether generated fields may be of fur | [Generating arms](generating.md) |

Every service keeps nothing between two calls.
