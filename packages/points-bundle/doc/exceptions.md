# Exceptions

The bundle raises the library's exceptions (`Tilttek\Points\Exception\PointsException`): `InvalidPoints` and
`UnknownCurrency` for a wrong currency, rule or timezone in the configuration, when the container is compiled, and
`MovementConflict`, `InsufficientPoints`, `UnknownCurrency` and `UnknownMovement` as the library does. Its own:

| Exception | Raised when | Message, for instance |
|---|---|---|
| `UnexpectedValueException` | A row of the ledger does not read | `An integer column holds string.` |
| `LogicException` | A movement is neither written nor found after its insertion, or a payment's lock row cannot be updated, which only a broken table can cause | `The movement "gift:1" is neither written nor found.` |
| `InvalidConfigurationException` | A key of `tilttek_points` is out of its bounds | Symfony's message |
