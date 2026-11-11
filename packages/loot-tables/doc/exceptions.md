# Exceptions

Every exception of the library implements `Tilttek\LootTables\Exception\LootException`: catch it to catch them all.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidLoot` | `InvalidArgumentException` | An entry, a table, a box, a catalogue, the faces of a result or a pending draw is refused |
| `UnknownLoot` | `OutOfBoundsException` | A catalogue holds no box or no table of that key |
| `InvalidCode` | `InvalidArgumentException` | A shop code or a draw text does not read as the dice of its box, or the box has no shop code |

`LootDrawer::verify()` throws nothing: a proof that does not read is false.
