# The format

A system is written as a document in YAML or JSON, with the same fields in both. `SystemReader` reads it and
`SystemWriter` writes it back; a system written then read is equal to the system it was.

## A system

```yaml
format: tilttek-rpg-systems/1
locale: en
system:
  key: lanterns
  names: { en: Lanterns in the Dark, fr: Lanternes dans le noir }
  version: '1.0'
source:
  title: Lanterns in the Dark
  licence: owned
  author: The application
attributes:
  - key: grit
    names: { en: Grit, fr: Cran }
    abbreviations: { en: GRT, fr: CRN }
    minimum: 1
    maximum: 6
dice:
  - key: desperate
    notation: 3d6kl2
    names: Desperate roll
terms:
  - key: range.near
    category: range
    names: Near
    descriptions: Within a few steps.
    source:
      title: Open ranges
      licence: CC-BY-4.0
      attribution: Ranges from Open ranges, under CC BY 4.0.
      url: https://example.org/ranges
```

| Field | Required | What it holds |
|---|---|---|
| `format` | Yes | `tilttek-rpg-systems/1` |
| `kind` | No | `system`, the default |
| `locale` | No | The locale of a text written alone, `names: Near` rather than `names: { en: Near }`; `en` by default |
| `system` | Yes | `key`, `names`, and an optional `version` |
| `source` | Yes | The provenance of the system, and of every record that states none |
| `attributes` | No | A list of `key`, `names`, `abbreviations`, `descriptions`, `minimum`, `maximum`, `source` |
| `dice` | No | A list of `key`, `notation`, `names`, `source` |
| `terms` | No | A list of `key`, `names`, `category`, `descriptions`, `source` |
| `variants` | No | The rule variants: a list of `key`, `names`, `descriptions`, `source` |
| `difficulties` | No | The difficulty numbers: a list of `key`, `names`, `descriptions`, `method`, `bands` — each `key`, `names`, `number` —, `numbers`, `source` |
| `roles` | No | The party roles: a list of `key`, `names`, `descriptions`, `source` |
| `translations` | No | The provenances of translations, a list by locale: `{ es: [ { title: …, licence: … } ] }` |

A source holds `title` and `licence`, and `attribution`, `author` and `url` as its licence needs
([Sources and attribution](attribution.md)). Texts are a mapping of locale to text, or a single text in the
document's locale. A version written `5.2` without quotes is read as a number and kept as the text `5.2`; a version
such as `5.10` is quoted, or it reads `5.1`.

## Rule variants, difficulty numbers and party roles

```yaml
variants:
  - key: long-night
    names: { en: The long night, fr: La longue nuit }
difficulties:
  - key: check
    names: Difficulty of an action
    method: check
    bands:
      - { key: easy, names: Easy, number: 6 }
      - { key: hard, names: Hard, number: 9 }
  - key: encounter
    names: Threat of a scene
    method: budget
    bands:
      - { key: calm, names: Calm }
      - { key: tense, names: Tense }
    numbers:
      budgets:
        1: { calm: 2, tense: 4 }
      costs: { 1/2: 1, 1: 2 }
roles:
  - key: bearer
    names: { en: Lantern bearer, fr: Porte-lanterne }
```

The `numbers` of difficulty numbers are a mapping of labels to whole numbers or to mappings of them, three levels deep
at most: a label written `1` reads as the number 1 and stands for the same label
([Systems and records](systems-and-records.md#difficulty-numbers)).

## Localisations

A localisation document lays the texts of one more language over a system, with the source of the translation:

```yaml
format: tilttek-rpg-systems/1
kind: localisation
system: lanterns
locale: es
source:
  title: Linternas en la oscuridad, traducción
  licence: CC-BY-4.0
  attribution: Spanish translation of Lanterns in the Dark, under CC BY 4.0.
name: Linternas en la oscuridad
attributes:
  grit: { name: Temple, abbreviation: TMP }
dice:
  desperate: Tirada desesperada
terms:
  range.near: { name: Cerca, description: A pocos pasos. }
```

`system`, `locale` and `source` are required. An attribute takes `name`, `abbreviation` and `description`, a term, a
rule variant and a party role `name` and `description`, a dice expression its name, difficulty numbers `name`,
`description` and `bands`, the names of their bands by key:

```yaml
variants:
  long-night: { name: La larga noche }
difficulties:
  check: { name: Dificultad de una acción, bands: { easy: Fácil, hard: Difícil } }
roles:
  bearer: { name: Portador de la linterna }
```

A key the system does not hold, a band included, is refused.

## Reading

```php
$reader = new SystemReader();

$systems = $reader->load('lanterns.yaml', 'lanterns.es.yaml');   // the systems first, then each localisation
$system = $reader->system($reader->decode($json, Syntax::Json));
$spanish = $reader->localise($system, $reader->decode($yaml, Syntax::Yaml));
```

`load()` takes files ending in `.yaml`, `.yml` or `.json`, reads every system, then lays each localisation over the
system it names, whatever the order of the files. `decode()` reads a text; `system()`, `localisation()` and
`localise()` read a decoded document. `provenance()` reads a decoded `source` mapping alone, the way a
[data package](data-packages.md)'s manifest holds it.

The reader is strict, so that a typing mistake is reported rather than silently dropped:

- a field it does not know is refused, with the place it was found: `attributes[0]: "rank" is not one of key, names, …`;
- a licence outside the closed list is refused ([Sources and attribution](attribution.md));
- YAML is read without objects and without tags, JSON to a depth of 64;
- a document longer than 2 000 000 bytes is refused before being decoded — the limit is the reader's argument —, a
  list of more than 10 000 records before being read.

## Writing

```php
$writer = new SystemWriter();

$writer->toYaml($system);
$writer->toJson($system);
$writer->toArray($system);
```

The writer writes the system's source once, and a record's source only where it differs; texts are written as
mappings of locale to text, empty fields are left out, and the provenances of the translations go under
`translations`.
