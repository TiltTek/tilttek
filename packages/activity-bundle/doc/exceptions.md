# Exceptions

The bundle raises the library's exceptions (`Tilttek\Activity\Exception\ActivityException`): `InvalidActivity` for a
wrong verb pattern in the configuration, when the container is compiled, `UnknownVerb`, `IdempotencyConflict`,
`UnknownProjection` and `InvalidProjection` as the library does. Its own:

| Exception | Raised when | Message, for instance |
|---|---|---|
| `UnexpectedValueException` | A row of the journal or a checkpoint does not read | `A context column holds JSON that is not an object.` |
| `LogicException` | An entry is neither written nor found after its insertion, which only a broken table can cause | `The recorded entry of the key "k" is neither written nor found.` |
| `InvalidConfigurationException` | `store` is not `doctrine` or `memory`, or `batch_size` is below 1 | Symfony's message |
