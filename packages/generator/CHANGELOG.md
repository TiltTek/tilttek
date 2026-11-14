# Changelog

All notable changes to `tilttek/generator` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

### Added

- The budget of a result checked inside its templates: each turn of a `for` loop and each `{% set %}` is a step,
  counted against `Limits::$steps` (10 000), with the memory the result has grown by checked against
  `Limits::$megabytes` (16) and the times of the result and of the draw checked at each step.
- `between(min, max)` in the templates: a whole number in a range, with the draw's randomness, counted as a pick.
- `generate(name, count, with: {…})`: values given to the generator called, as variables of its variables, body and
  fields.
- Attributions: `source`, `licence` and `attribution` on a list, an entry or a generator (`Attribution`), an entry's own
  taking the place of its list's; `DrawResult::$attributions`, once each in the order the draw first used them, and
  `attributions` in `DrawResult::toArray()`.
- `TraceryImporter`: Tracery grammars into lists and generators — symbols, `#symbol#`, the modifiers `.capitalize`,
  `.capitalizeAll`, `.a` and `.s`, actions `[name:rule]` and `[name:one,other]` whose variables are given to the
  generators drawn after them, as far down as they read them —, with a prefix, an attribution and a language.
- `PerchanceImporter`: tables in the subset of Perchance's syntax without JavaScript, as the specification of the
  project perhaps delimits it — sections, weights, references, `selectUnique` and `selectMany`, inline choices,
  ranges and dice, comments, fenced blocks of Markdown —, with a prefix, an attribution and a language.
- `Imported`: the document an import made, the generator to start from, and the document read into a catalog.
- `MapSchema`: a map exported as JSON with the identifier and the version of its schema, and the JSON Schema
  (draft 2020-12) of square, hex and graph maps, shipped as `schema/map.v1.json`.
- Fuzz targets of the two importers, `tracery` and `perchance`.
- The documentation of the imports, the attributions, the budget of a result and the export of maps.

### Changed

- `replace` and `join` in the templates are the generator's own: they refuse to make a text longer than
  `Limits::$bytes` (65 536), measured before the text is made, and refuse a part that is not a text.
- The time limits are also checked at each step inside a template, so that a loop that never picks stops at the turn
  that crosses them.
- `source`, `licence` and `attribution` are no longer field names of an entry.
- The documentation says that templates written by others — members of a site, authors of an imported grammar — may be
  loaded and drawn within the limits.

### Fixed

- The table `hook`, in English and in French: the entry whose text holds a comma is quoted, so that it is read whole
  instead of cut at the comma; Symfony Yaml 8.2.0-BETA2 refuses the unquoted form.
- A list or a generator whose name is made of digits only, which YAML and JSON decode as an integer key, is read.

### Security

- A template could loop over picks without counting the turns, and grow a text by `~`, `replace` or `join` until the
  memory ran out: the steps, the memory and the bytes made are now bounded inside each render.

## 0.1.0-alpha - 2026-11-12

### Added

- The definitions: `Entry` with its text, weight, fields and tags, read like an array; `EntryList`; `Template`;
  `TextBody` with its filters, `ObjectBody` with `TemplateField` and `GenerateField`, `GridBody` with `MapLayout` square
  or hex, `GraphBody` with fixed or rolled nodes and extra passages; `GeneratorDefinition` with its variables and
  language and its state of `StateValue`s, start and next templates, at most 32 by names that a variable or a field of
  the generator does not take; `MarkovChain` with its order, its lengths and whether entries come out; `EntryList` that
  invents words by a chain; `Catalog` by name, refusing duplicates.
- `Reader` and `FileLoader`: YAML and JSON documents, one or many lists and generators by file, a list as a sequence of
  entries or as a map of its `entries` and its `markov` chain, a generator's `state`, directories searched in depth in
  the order of their paths, and each error with its place; `Reader::isName()`, which a name with a line break after it
  does not pass.
- `Generator`: every template, a state's included, compiled in Twig's sandbox when the catalog is built, and every
  problem listed in one `InvalidCatalog` — syntax, tags, filters, functions and tests outside the sandbox, the range
  operator, unknown lists, tags and generators, unknown languages and filters, dice expressions that do not parse.
- The templates' functions: `pick` by weight, by tag, several without duplicates, with exclusions, the entries of a list
  filtered on the same tags kept from one pick to the next with their running weights, up to 64 filters by list;
  `generate`, once or several times without duplicates; `roll` on `tilttek/dice`; variables shared by a result;
  structured results whose fields see the earlier ones; text with its spaces made one.
- The grammars and their filters `elide`, `article`, `agree`, `plural` and `upper_first`: French elision, forms before a
  vowel, contraction, aspirated h, articles, feminine and plural with their exceptions; English `a` and `an`, articles
  and plural with its irregular words; Spanish `el` before a stressed a, `del` and `al`, articles with the neuter `lo`,
  feminine and plural with their written accents; German nominative articles by gender, contractions of a preposition
  and the article, plural by ending with its irregular words; Italian articles by the next sound, elision, articulated
  prepositions and partitive, feminine and plural with their irregular words; Portuguese articles, contractions with
  `de`, `em`, `a` and `por`, feminine and plural with their irregular words, and the count 0 of Brazil and of Portugal;
  the mark `Grammar::KEEP` (`^`) that keeps a word and the word before it as written; the `Grammar` port, documented, by
  which an application adds or replaces a language; `Grammars` by locale, usual form of the locale or language.
- Markov lists: a pick invents a word letter by letter from the runs of letters the chain learnt of the entries, counted
  by their weight, filtered by the tags of the pick, which the word carries; capitalised when every entry is; within the
  shortest and the longest length; never an entry unless the chain allows it; several without duplicates and none
  excluded, compared without regard to case; 50 attempts by word, each counted as a pick, then `ExhaustedList`; the
  chain learnt once by list and tags.
- Tables with a memory: a generator's state, each value given by its start template when the previous state lacks it, by
  its next template otherwise, or kept, its templates seeing `previous` and `state`, then the variables and the body
  seeing both; threaded through the results of a draw, a unique draw dropping a repeated result and the state it left; a
  value longer than 1024 characters stops the draw; another generator calling it starts it again.
- Random tables in `tables/`, each a file standing alone under `table.<name>`: encounters by terrain, magic items, loot
  with unidentified items, shops with their merchant and stock, adventure hooks, rumours of a place, a solo play oracle
  weighing the odds and a chaos factor, and fortunes from cards or tables; written in English in `tables/en/` and in
  French in `tables/fr/`, the two versions of a table with the same lists, generators, state values and fields;
  `Tables` with their names, languages, and the directory and files of each language.
- The maps: square cells with `x` and `y`, hexagons with offset and axial coordinates, graphs whose nodes are all
  reachable, with extra passages, in order.
- Draws: `Draw` with a count, uniqueness, a seed and the previous state, at most 32 names to texts of at most 1024
  characters, in UTF-8, `DrawResult` that says when it is short and carries the state a generator with a memory is left
  in, unique results within a bounded number of attempts, seeded draws on `Xoshiro256StarStar`, and `Limits` on depth,
  picks, time of a result, attempts, results, cells and time of the whole draw, the times checked at each pick and
  before each template is rendered, with `Timer` and `SystemTimer`; nothing of a draw kept for the next one but the entries
  filtered by tag, so that a long-running process can serve one draw after another.
- Exceptions under `GeneratorException`: `InvalidDefinition`, `InvalidCatalog` with its `Problem`s, `UnknownGenerator`,
  `UnknownList`, `UnknownTag`, `ExhaustedList`, `LimitExceeded`, `GenerationFailed`; an exception of the library thrown
  inside a template reaches the caller unwrapped.
- Neutral examples: syllabic names, names invented by a Markov chain, a yes-or-no oracle, an inn in French, a village, a
  dungeon, and three tables with a memory — the weather as a hex flower, a journey and a hexcrawl.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
