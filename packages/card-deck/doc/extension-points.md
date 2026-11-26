# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\GameStore` | The store of its games, on its own database | [Games and stores](games-and-stores.md#a-store-of-your-own) |
| `Deck` | The cards of its games, declared as data | [Cards and zones](cards-and-zones.md#decks) |
| The rules of the game | Whose turn it is and which player may play which gesture, checked before `CardTable::play()` | [Gestures](gestures.md) |
| The pushing of views | Each player's own view, sent after each gesture | [Player views](player-views.md) |

The cards, zones, gestures and views are values; the gestures are a closed set the codec reads. `CardTable` keeps no
state between two calls.
