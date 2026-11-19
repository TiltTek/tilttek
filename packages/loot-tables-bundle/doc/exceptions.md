# Exceptions

| Exception | Thrown when |
|---|---|
| `Domain\Exception\LootDrawRefused` (`DomainException`) | A unit never committed is asked to be drawn while a commitment is required; a draw is revealed or entered twice, or revealed from another seed; a text option has no list a die draws from; physical dice are entered for a line or an option that is not a box left to draw, or not one code per unit |
| `Tilttek\LootTables\Exception\InvalidLoot` | The configuration declares a table or a box the library refuses, when the container is compiled |
| `Tilttek\LootTables\Exception\InvalidCode` | A shop code entered at a register does not read as the dice of its box |
| `Tilttek\LootTables\Exception\UnknownLoot` | A draw names a box the catalogue no longer holds |

A `LootDrawRefused` thrown while the order is placed — a text option without a list to draw from — comes out of the
workflow's `place` transition: the order is not to be stored placed.

The commerce core refuses with its own `DrawRefused` an order not paid, or an option whose drawer no service supports.
