# Exceptions

Every exception of the library implements `Tilttek\WargameArmy\Exception\WargameArmyException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidCatalogue` | A unit, an option, a group, a detachment type, a slot, a constraint or a catalogue is refused, or does not read | `The group "weapon" has two options "sword".` |
| `InvalidArmyList` | An army list, a detachment or a unit selection holds a value out of its bounds, or does not read | `The models of a unit is from 1 to 1000; 0 is not.` |
| `UnknownCatalogue` | The application does not provide a list's catalogue | `The catalogue "ember-vale" is not provided.` |

`InvalidCatalogue` and `InvalidArmyList` extend `InvalidArgumentException`; `UnknownCatalogue` extends
`RuntimeException`. A list that breaks its catalogue's rules raises nothing: the validator reports it.
