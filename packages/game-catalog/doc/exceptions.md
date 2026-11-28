# Exceptions

Every exception of the library implements `Tilttek\GameCatalog\Exception\CatalogException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidEntry` | A value of an entry, an identifier, a link, an erratum, a correction or a barcode is out of its shape or its bounds | `The name of a game is 1 to 200 characters.` |
| `InvalidVote` | A ballot holds a vote out of its scale, more than 30 numbers of players, or no vote | `The weight is from 1 to 5.` |
| `InvalidCollectionFile` | A collection file cannot be read as a whole, or columns or limits are not valid | `A collection file is at most 2097152 bytes.` |
| `CatalogRefused` | An identifier, an origin or a barcode another entry holds; a link over the limit; a correction already decided, changing nothing, or over the limit of a member; a source sending another's entries | `The entry e-1 already holds rpggeek:7.` |
| `CorrectionConflict` | A field a correction changes was changed on the entry since it was proposed; its `fields` name them | `The correction c-2 changes year, changed on the entry since it was proposed.` |
| `CatalogConflict` | The entry or the correction changed, or was added, since it was read: nothing is kept | `The entry e-1 changed since it was read.` |
| `EntryNotFound` | No entry has the identifier | `No entry of the catalogue has the identifier e-9.` |
| `CorrectionNotFound` | No correction has the identifier | `No correction has the identifier c-9.` |
