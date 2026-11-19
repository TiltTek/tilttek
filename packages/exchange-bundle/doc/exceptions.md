# Exceptions

The bundle raises the exceptions of `tilttek/exchange` and `tilttek/points`. Its own cases:

| Exception | Raised when |
|---|---|
| `Tilttek\Exchange\Exception\InvalidExchange` | The container is compiled with a reward, a duration or a timezone that does not hold, a catalogue in the database without the `doctrine` store, rewards configured with the `doctrine` catalogue, a delay without `member_since`, or trades without an inventory |
| `Symfony\Component\Config\Definition\Exception\InvalidConfigurationException` | A key of `tilttek_exchange` holds a value of the wrong type or out of range |
| `UnexpectedValueException` | A column read back holds what it cannot: a text column a number, the conditions of a reward something else than a JSON list of strings |
| `LogicException` | A reward's lock row is neither written nor found |
