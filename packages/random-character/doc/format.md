# The format

A document of creation rules is a YAML or JSON mapping that names the format `tilttek-random-character/1`.
`Format\RulesReader` reads it into a `RulesCatalogue`:

```php
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RandomCharacter\Format\Syntax;

$catalogue = new RulesReader()->load('rules/lanterns.yaml');          // .yaml, .yml or .json
$catalogue = new RulesReader()->parse($text, Syntax::Json);
$catalogue = new RulesReader()->catalogue($decodedDocument);
```

```yaml
format: tilttek-random-character/1
system: lanterns
rules:
  - key: lanterns.wanderer
    stats:
      method: rolled-array
      roll: 1d4+1
      attributes: [grit, wits, glow]
    fields:
      - { key: lineage, generator: lanterns.lineage }
      - { key: name, generator: 'lanterns.name.{lineage}' }
      - { key: traits, generator: lanterns.trait, count: 2, unique: true }
      - { key: age, roll: 2d10+14 }
```

## The document

| Field | Required | Holds |
|---|---|---|
| `format` | Yes | `tilttek-random-character/1`. |
| `system` | No | The system of the rules that do not name theirs. |
| `rules` | Yes | A list of 1 to 1,000 rules. |

## Rules

| Field | Required | Holds |
|---|---|---|
| `key` | Yes | The key of the rules. |
| `system` | When the document names none | The key of the system in `tilttek/rpg-systems`. |
| `stats` | When there are no fields | The stats, below. |
| `fields` | When there are no stats | A list of 1 to 64 fields, below, in the order they are rolled. |

## Stats

| Field | Required | Holds |
|---|---|---|
| `method` | Yes | `each`, `rolled-array` or `array`. |
| `attributes` | Yes | A list of 1 to 32 attribute keys of the system. |
| `roll` | For `each` and `rolled-array` | The roll of each value, in a notation of `tilttek/dice`. |
| `values` | For `array` | One whole number per attribute. |

## Fields

| Field | Required | Holds |
|---|---|---|
| `key` | Yes | The key of the field. |
| `generator` | Either this or `roll` | The name of the generator, with its placeholders: `names.{lineage}`. |
| `roll` | Either this or `generator` | The roll, in a notation of `tilttek/dice`. |
| `count` | No | How many values, 1 to 16; 1 by default. |
| `unique` | No | For a generated field, `true` for different results; `false` by default. |

## Refusals and bounds

Every refusal is an `InvalidDocument` whose message starts with the place of the field: `rules[0].fields[2].count: a
whole number is expected.`, `rules[1].stats: the stats of a fixed array have no roll, and one value per attribute.`
A mapping holds only the fields of its table; YAML tags that build objects and JSON nested 64 levels deep are
refused. A document is read up to 1 MiB by default, `RulesReader::MAX_BYTES`; the constructor takes another bound.

The reader checks what a document says, not what it names: whether the system has the attributes, and whether the
generators exist, is checked by `CharacterRoller::check()` against the application's systems and generators.
