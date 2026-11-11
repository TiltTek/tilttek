# Reference

| Type | Kind | Role |
|---|---|---|
| `LootEntry` | `final readonly class` | An entry: key, name, image, slot |
| `LootTable` | `final readonly class` | The entries rolled with one die |
| `LootBox` | `final readonly class` | The tables a product rolls, in order |
| `LootCatalog` | `final readonly class` | The tables and boxes of an application, by key |
| `Reading\CatalogReader` | `final readonly class` | A catalogue read from plain arrays |
| `Draw\PendingDraw` | `final readonly class` | A committed draw: secret seed and nonce; checks the client seed of a reveal |
| `Draw\LootDrawer` | `final readonly class` | Reveals a box from a pending draw, verifies a proof |
| `Draw\RevealedDraw` | `final readonly class` | A revealed draw, its result and its roll; its proof |
| `Draw\LootResult` | `final readonly class` | One face and one entry per table, and their source |
| `Draw\Pick` | `final readonly class` | The face and the entry of one table |
| `Draw\DrawSource` | `enum` | `Verifiable` or `Physical` |
| `Code\ShopCode` | `final readonly class` | One character per die, written and read |
| `Code\DrawText` | `final readonly class` | The faces and the names on one line, written and read |
| `Exception\LootException` | `interface` | Every exception of the library |
| `Exception\InvalidLoot` | `final class` | A definition refused |
| `Exception\UnknownLoot` | `final class` | A box or a table the catalogue does not hold |
| `Exception\InvalidCode` | `final class` | A shop code or a draw text that does not read |
