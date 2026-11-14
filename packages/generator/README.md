# tilttek/generator

**Random generators written as data: lists, and Twig templates that pick in them.**

tilttek/generator draws names, characters, inns, treasures, rumours or maps from lists and templates that an author
writes in YAML or JSON, without code. A list holds weighted entries with fields and tags; a template is a Twig
expression in a sandbox that picks in the lists by weight and by tag, calls other generators, rolls dice, and shapes the
text with Twig's filters and with the grammar of a language — elision and contraction (`le arbre` → `l'arbre`, `de el
rey` → `del rey`, `di il re` → `del re`), articles by gender and number, agreement and plurals in French, English,
Spanish, German, Italian and Portuguese, and any other language an application adds behind the `Grammar` port. A
generator returns a text, a structured result (an inn with its name, its keeper, its staff and its menu) or a map
(square cells, hexagons, or rooms joined by passages). A list may invent new words from its entries by a Markov chain
instead of giving them back; a generator may carry a state from one draw to the next — a table with a memory, for the
weather of a campaign, a journey or the exploration of a map — which the caller keeps and passes back. A list, an entry
and a generator say where their text comes from — source, licence, attribution — and a draw gives the attributions of
what its results used. Tracery grammars and tables in the subset of Perchance's syntax without JavaScript are imported
into lists and generators; a map is exported as versioned JSON with its JSON Schema. Random tables
ready to draw ship in `tables/`, in English and in French: encounters, magic items, loot with unidentified items, shops,
adventure hooks, rumours, a solo play oracle and fortunes. It does not keep lists or states for anyone, show results or
serve them: the application holds its own lists, states and pages, and the bundle serves draws.

It is for any PHP code that makes random content for people: a name generator, a game master's toolbox, a solo play
oracle, a random table in a book, a dungeon for a virtual table. `tilttek/generator-bundle` integrates it with Symfony;
an application without Symfony takes the library alone.

It is a PHP library on Twig, whose sandbox runs the templates, Symfony Yaml, which reads the files, and `tilttek/dice`,
which rolls the dice. Its randomness is a `Random\Randomizer` the caller passes, or the seed of a draw, which makes the
same draw come again. Every template is compiled and checked when the catalog is built: a template that does not
compile, leaves the sandbox, or names a list, a tag or a generator that does not exist is an error then, not an empty
text later. Each result has a budget checked inside its templates — steps of loops and assignments, memory, time, the
length of what `replace` and `join` make —, so that templates written by others are drawn at a bounded cost.

The design is small: a `Reader` or a `FileLoader` gives a `Catalog` of `EntryList` and `GeneratorDefinition`, a
`Generator` checks it and draws from it into a `DrawResult`, and the `Grammar` classes give the filters of each
language; `Tables` points to the tables the library ships.

## Status

| Part | State |
|---|---|
| Lists: weighted entries, fields, tags; documents in YAML and JSON, one or many by file; errors with their place | Implemented and tested |
| Markov lists: words invented from the entries, order and lengths, entries kept out, tags of the pick | Implemented and tested |
| Tables with a memory: a state given by start and next templates, threaded through a draw and returned | Implemented and tested |
| Random tables in `tables/`: encounters, magic items, loot, shops, hooks, rumours, solo play oracle, fortunes, in English and French | Implemented and tested |
| Templates in Twig's sandbox: `pick` by weight, by tag, several without duplicates, with exclusions; `generate`, with variables; `roll`; `between` | Implemented and tested |
| The budget of a result checked inside its templates: steps of loops and assignments, memory, time; `replace` and `join` bounded | Implemented and tested |
| Attributions: source, licence and attribution of a list, an entry or a generator, given with the results that used them | Implemented and tested |
| Imports of Tracery grammars and of tables in the subset of Perchance's syntax without JavaScript | Implemented and tested |
| Maps exported as versioned JSON, with their JSON Schema | Implemented and tested |
| Checks when the catalog is built: syntax, sandbox, unknown lists, tags and generators, the range operator | Implemented and tested |
| Variables shared by a result; structured results; square and hex maps; graphs of nodes and passages | Implemented and tested |
| Grammar filters: French elision, contraction, articles, agreement and plural; English `a`/`an`, articles and plural | Implemented and tested |
| Spanish, German, Italian and Portuguese grammars: articles by gender and number, contractions and elision, feminine and plural | Implemented and tested |
| The `Grammar` port, by which an application adds or replaces a language | Implemented and documented |
| Draws: counts, unique results with a bounded number of attempts, seeds; limits of depth, picks, steps, memory, time, results and cells | Implemented and tested |
| Exceptions under one `GeneratorException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Lists and generators](doc/lists-and-generators.md) | The documents: lists, entries, fields, tags, Markov lists, generators and their kinds, and where their text comes from. |
| [Templates](doc/templates.md) | `pick`, `generate`, `roll`, `between`, variables, the filters, the sandbox and the budget of a result. |
| [Grammar](doc/grammar.md) | Elision, articles, agreement and plural in French, English, Spanish, German, Italian and Portuguese; adding a language. |
| [Maps](doc/maps.md) | Square and hex maps, graphs, their coordinates, their results and their JSON export. |
| [Drawing](doc/drawing.md) | `Generator`, `Draw`, `DrawResult`, attributions, states, uniqueness, seeds, randomness and limits. |
| [Imports](doc/imports.md) | Tracery grammars and Perchance tables into lists and generators. |
| [Tables with a memory](doc/memory.md) | A state carried from one draw to the next: writing it, drawing with it, and the weather, journey and hexcrawl examples. |
| [Random tables](doc/tables.md) | The tables of `tables/` in English and French, what each one gives, and the solo play oracle. |
| [Extension points](doc/extension-points.md) | The grammar port, the clock, the randomness and the dice a generator takes. |
| [Exceptions](doc/exceptions.md) | `GeneratorException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that draws. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

The `examples/` directory holds neutral examples: syllabic names, names invented by a Markov chain, a yes-or-no oracle,
a French inn, a village, a dungeon, and three tables with a memory — the weather as a hex flower, a journey and a
hexcrawl.

## Quick start

```sh
composer require tilttek/generator
```

```yaml
# generators/inn.yaml
lists:
  inn.animal:
    - { text: sanglier, gender: m }
    - { text: oie, gender: f }
  inn.adjective:
    - { text: doré }
    - { text: vieux, f: vieille }
generators:
  inn.name:
    locale: fr
    templates:
      - "{% set animal = pick('inn.animal') %}{{ ('à ' ~ animal|article ~ ' ' ~ pick('inn.adjective')|agree(animal))|elide }}"
    filters: [upper_first]
```

```php
use Tilttek\Generator\Draw;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;

$generator = new Generator(new FileLoader()->catalog(['generators']));

$generator->draw('inn.name', new Draw(3, unique: true))->results;   // ["À l'oie dorée", 'Au sanglier vieux', …]
$generator->draw('inn.name', new Draw(seed: 'table 7'))->first();   // the same text for the same seed
```

## Requirements

PHP 8.5 or later, with the `mbstring` extension.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
