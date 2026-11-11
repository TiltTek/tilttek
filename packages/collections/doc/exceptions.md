# Exceptions

Every exception of the library implements `Tilttek\Collections\Exception\CollectionsException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidCollection` | A code, a text, a rarity, a die, a reward, an item, a set, a pack, a catalog, a key, a day, a date, a seed or a verb list is refused | `"Pirate" is not a item code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `NotFound` | A rarity, an item, a set, a pack or a copy is asked for by a code none has | `No item is named "parrot".` |
| `CollectionRefused` | A key already gave something else, a copy does not belong to the member who gives it or changed hands meanwhile, a copy chosen is not a die | `The key "order:1" already gave another copy.` |

The verb patterns of the projection are checked by `tilttek/activity`.
