# Exceptions

Every exception of the library implements `Exception\MathTradeException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `InvalidTradeList` | An identifier out of bounds, two participants or offers with one identifier, a participant without offers, a wish for itself, for nobody's offer, for its own participant's offer or for one offer twice, a group of fewer than two, limits out of their bounds, a JSON document that is not a trade list of the format, an offer looked up that nobody makes | What is wrong, naming the participant, the offer or the key |
| `LimitExceeded` | A list or a JSON document larger than its `Limits` allow | The limit that was passed, such as `The trade list holds more than 5000 offers.` |
| `InvalidSeed` | A seed empty, longer than 256 bytes, not UTF-8, or holding a control character | `A seed is 1 to 256 bytes of UTF-8 without a control character.` |

`InvalidTradeList` and `InvalidSeed` extend `InvalidArgumentException`; `LimitExceeded` extends `LengthException`.

The messages name the application's identifiers, which the participant who wrote the list knows: an application may
show them to that participant as they are.
