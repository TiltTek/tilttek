# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Rules

| Type | Role |
|---|---|
| `CreationRules` | The rules of a system: their key, the system's key, the stats and the fields in order. |
| `Stats` | The attributes the rules fill, the method, and the roll or the fixed values. |
| `StatMethod` | Each in order, rolled array, or fixed array. |
| `Field` | A field drawn from a generator, named with placeholders, or rolled with dice; one value or up to 16. |
| `FieldKind` | Generated or rolled. |
| `RulesCatalogue` | The rules of an application, by key and by system. |

## Rolling

| Type | Role |
|---|---|
| `CharacterRoller` | The service: checks rules against the systems and generators, and rolls characters from a seed given or drawn. |
| `RandomCharacter` | A character: rules, system, seed, stats, fields, attributions and the values edited by hand. |
| `Contract\CharacterObserver` | Told of each character rolled. |
| `Event\CharacterRolled` | A roll, by the keys of the rules and of the system, and whether the seed was given. |

## Format

| Type | Role |
|---|---|
| `Format\RulesReader` | Reads a YAML or JSON document of the format `tilttek-random-character/1` into a catalogue. |
| `Format\Syntax` | YAML or JSON, and the syntax of a file by its extension. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\RandomCharacterException` | The interface every exception of the library implements. |
| `Exception\InvalidRules` | Rules that cannot be built or rolled. |
| `Exception\InvalidDocument` | A document that cannot be read, at its place. |
| `Exception\InvalidSeed` | A seed that cannot replay a character. |
| `Exception\InvalidEdit` | A change a character cannot take. |
| `Exception\UnknownRules` | No rules of the key. |
| `Exception\RollFailed` | A roll that could not give a character. |

## Internal

| Type | Role |
|---|---|
| `Internal\Check` | The checks of keys, field keys and dice notations. |
| `Internal\Fields` | One mapping of a document, read field by field. |
