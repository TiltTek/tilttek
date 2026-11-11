# Exceptions

The bundle raises the library's exceptions — see the library's documentation —, and:

| Raised | When |
|---|---|
| `InvalidConfigurationException` | An option is out of its values: an unknown store or catalog, an empty name, an offer lifetime that is not an ISO 8601 duration of some time, quests configured with `catalog: doctrine` |
| `InvalidQuest` at compile time | A configured quest, a recurrence or the timezone is refused, with the library's message |
| `InvalidQuest` from `DbalCatalog` | A definition in the table is not JSON or not a list of values, or a save or a removal would break a chain |
| `UnexpectedValueException` | A row holds a value of the wrong type |
