# Lists and generators

A catalog is read from decoded YAML or JSON documents. A document holds `lists` and `generators` maps by name, or a
single list, or a single generator:

```yaml
lists:
  name.elf.first: [Aelis, Lórien]
generators:
  name.elf:
    templates: "{{ pick('name.elf.first') }}"
```

```yaml
list: weapon
entries: [sword, bow]
```

```yaml
generator: weapon.name
templates: "{{ pick('weapon')|capitalize }}"
```

`FileLoader` reads `.yaml`, `.yml` and `.json` files, given one by one or by directory, searched in depth, in the order
of their paths. `Reader` reads documents already decoded, by the name of their source. A document of the wrong shape is
an `InvalidDefinition` that says where: `inn.yaml > generators > inn > fields > staff: …`. Two lists or two generators
of the same name are an error, wherever they are.

## Names

A name is made of letters, digits, `_` and `-`, in parts joined by dots: `name.fantasy.elf.female.first`. The dots
only order the names for people; the library does not read them. `Reader::isName()` says whether a text is a name.
A name of digits only, such as `12`, which YAML and JSON decode as a number when it is a key, is read as a name.

## Lists and entries

A list is a sequence of at least one entry. An entry is a text, or a map:

```yaml
lists:
  weapon:
    - dagger
    - { text: épée, weight: 3, gender: f, price: 12, tags: { kind: blade, era: [old, middle] } }
```

| Key | Meaning |
|---|---|
| `text` | What the entry prints. Required in a map. |
| `weight` | A whole number of at least 1, 1 by default: an entry of weight 3 comes three times as often. |
| `tags` | A map of tags, each with a value or a sequence of values, on which `pick(…, where: …)` filters. |
| `source`, `licence`, `attribution` | Where this entry comes from, when not from where its list comes from; see [Where the text comes from](#where-the-text-comes-from). |
| any other key | A field: a scalar or a sequence of scalars, read in a template as `entry.price`. |

A list may also be written as a map of its `entries`, its `markov` chain and where it comes from; see [Markov
lists](#markov-lists) and [Where the text comes from](#where-the-text-comes-from).

What other generators write as ranges of indexes — "the first six adjectives only go with the first four nouns" — is
written here with tags. A filter on a tag that no entry of the list carries is an error, not an empty draw.

The grammar filters read the fields `gender` (`m`, `f` or `n`), `number` (`p` for a word that is always plural),
`plural`, and, for adjectives, `m`, `f`, `mp` and `fp`; a field may also be given as a tag of the same name.

## Markov lists

A list with a `markov` chain does not give its entries back: a pick invents a new word from them, letter by letter. The
chain learns, for each run of letters as long as its order, which letters follow and how often, counted by the weight
of the entries; a word is written from the start of a word to its end, in lower case, and capitalised when every entry
is.

```yaml
lists:
  name.elf:
    markov: { order: 2, min_length: 4, max_length: 9 }
    entries:
      - { text: Aelis, tags: { gender: f } }
      - { text: Lórien, tags: { gender: f } }
      - { text: Thalion, tags: { gender: m } }
```

| Key | Meaning |
|---|---|
| `order` | How many letters choose the next one, from 1 to 6, 2 by default: a higher order copies more of the entries. |
| `min_length` | The shortest word, in letters, 3 by default. |
| `max_length` | The longest word, from the shortest to 64, 16 by default. |
| `allow_entries` | Whether an entry may come out as it is, `false` by default. |

`markov: true` takes the defaults. `pick('name.elf', where: {gender: 'f'})` invents from the entries whose tags match,
and the word it invents carries the tags the pick asked for, so that `|article` and `|agree` read its gender.
`pick('name.elf', 3)` invents three different words, and `not:` excludes words, compared without regard to case. Each
attempt at a word counts as a pick: a pick tries `MarkovChain::ATTEMPTS` (50) times for each word it asks for, and a
single pick that invents nothing new fails with `ExhaustedList`. A list of few or very short entries invents few words:
lower the order, or allow the entries.

## Generators

A generator has exactly one body, and may have a language, variables and a state:

| Key | Meaning |
|---|---|
| `templates` | A template, or a sequence of templates, each a text or `{template: …, weight: …}`: one is chosen by weight for each result. |
| `filters` | With `templates` only: filters without arguments applied in order to the whole text, such as `upper_first`, `capitalize` or `elide`. |
| `fields` | A structured result: a map of fields, each a template or `{generate: name, count: 3}`. |
| `map` | A map of cells or a graph of nodes; see [Maps](maps.md). |
| `vars` | Templates by variable name, rendered once per result, in order, before the body; each sees the earlier ones. |
| `locale` | The language of the grammar filters, such as `fr` or `en_CA`; the generator's default otherwise. |
| `state` | Values carried from one result to the next: a table with a memory; see [Tables with a memory](memory.md). |
| `source`, `licence`, `attribution` | Where the text of its templates comes from; see [Where the text comes from](#where-the-text-comes-from). |

The fields of a structured result are filled in order, and each sees the earlier ones as variables:

```yaml
generators:
  inn:
    vars:
      keeper: "{{ generate('person.name') }}"
    fields:
      name: "The {{ pick('inn.animal')|capitalize }}"
      keeper: "{{ keeper }}"
      description: "{{ name }}, kept by {{ keeper }}"
      staff: { generate: person.name, count: 3 }
      menu: { generate: inn.dish, count: 4 }
```

A field filled by `generate` keeps the other generator's result as it is — a text, a structured result, a map — and
a count gives a list of different results.

## Where the text comes from

A list, an entry and a generator say where their text comes from by three texts, each optional but at least one
given, each of 1 to 1024 characters in UTF-8:

| Key | Meaning |
|---|---|
| `source` | Where the text was taken from: a book, a site, a set of tables. |
| `licence` | The licence it is under, best written as an SPDX identifier such as `CC-BY-4.0`. |
| `attribution` | The credit its licence asks to show, as it is to be shown. |

```yaml
lists:
  oracle.action:
    source: The tables of a shared game
    licence: CC-BY-4.0
    attribution: Oracle tables by A. Author, CC BY 4.0
    entries:
      - seek
      - { text: defend, source: Another book, licence: CC0-1.0 }
```

An entry's own texts take the place of its list's. A draw gives the attributions of every list, entry and generator
its results used ([Drawing](drawing.md#attributions)), which the application shows with them. The names `source`,
`licence` and `attribution` are not fields of an entry. The importers give an attribution to every list and
generator they make ([Imports](imports.md)).

## Neutral examples

The `examples/` directory of the package holds a few generators that load with `FileLoader` and that the tests draw:
syllabic names and names invented by a Markov chain, a yes-or-no oracle whose answers follow a chaos factor, an inn in
French, a village on a square grid, a dungeon of rooms, and three tables with a memory — the weather, a journey and the
exploration of a map of hexagons. They show the syntax; the lists of an application are its own. The `tables/`
directory holds random tables ready to draw; see [Random tables](tables.md).
