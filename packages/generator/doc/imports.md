# Imports

Two importers read generators written for other tools into the documents of the library: Tracery grammars, and tables
in the subset of Perchance's syntax that has no JavaScript. Each returns an `Imported`: its `document` — lists and
generators, as `Reader` reads them and as a YAML or JSON file would hold them — and its `start`, the generator to draw
from first.

```php
use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Import\TraceryImporter;

$imported = new TraceryImporter('story.', new Attribution(source: 'A grammar shared by its author', licence: 'CC0-1.0'))
    ->import(file_get_contents('grammar.json'));

$generator = new Generator($imported->catalog());
$generator->draw($imported->start)->first();

file_put_contents('config/generators/story.yaml', Yaml::dump($imported->document, 6));
```

Both take a prefix put before every name, so that an import does not meet the names of the catalog, and an
`Attribution` given to every list and generator they make, which the draws then give with their results
([Lists and generators](lists-and-generators.md#where-the-text-comes-from)). What they make is checked like any other
document when the `Generator` is built, and drawn within the [limits](drawing.md#limits); a grammar or tables from
someone else are imported like any other data.

A problem is an `InvalidDefinition` that says where: `tracery > origin > 0: "#" is not closed`,
`perchance > line 12: "[a.joinItems(1)]" is not supported: …`.

## Tracery

A Tracery grammar is a JSON object of symbols, each with a rule or a sequence of rules:

```json
{
    "origin": ["#[hero:#name#]story#"],
    "story": ["#hero.capitalize# met #animal.a#. #hero# was glad."],
    "name": ["ada", "bo"],
    "animal": ["owl", "ox"]
}
```

| Tracery | What it becomes |
|---|---|
| A symbol whose rules are plain text | A list, each rule an entry. |
| Any other symbol, and the symbol to start from | A generator, each rule a template of weight 1, in the import's language (`en` by default). |
| `#symbol#` | `pick('symbol')` for a list, `generate('symbol')` for a generator. |
| `.capitalize`, `.capitalizeAll`, `.a`, `.s` | The filters `upper_first`, `title`, `article('indefinite')` and `plural`. |
| `[name:#rule#]`, `[name:one,other]` | `{% set v_name = … %}`: the variable takes the rule's text, or one of its rules at random, for the rest of the rule. |
| `#name#` after an action | The variable; it takes the place of a symbol of the same name. |
| `\#`, `\[`, `\\` | The character as written. |

A generator drawn after an action receives the variables it reads — itself, or any generator it draws — by
`generate('story', with: {v_hero: v_hero})`: the import finds them through every level, so that `#hero#` in a symbol
drawn three levels down is the hero set at the top. The import starts from `origin`, or from the symbol named by the
fourth argument.

Refused: an unknown symbol, a variable that the symbol to start from reads before any action sets it, `POP`, an
action without a value (Tracery's functions), an action inside the value of another action, the other modifiers
(`.ed`, `.possessive`, `.replace(…)` and the like), a name that is not a name of the library, a document of more than
`TraceryImporter::MAX_BYTES` bytes or `MAX_SYMBOLS` symbols, a symbol of more than `MAX_SYMBOLS` rules, and actions and
tags nested more than `MAX_DEPTH` deep. A variable keeps its value for the rest of its rule: an action inside a tag
does not end with the tag as it does in Tracery.

## Perchance

The tables read are the subset of Perchance's syntax that has no JavaScript, as the specification of the Apache-2.0
project perhaps delimits it:

```text
title
  Beasts

animal
  dog
  cat ^2
  owl ^0.5      // a comment

coat colour
  red
  blue

output
  A {big|small^3} [coat colour] [animal] with {1-6} legs and 2d6+1 teeth.
  [animal.selectUnique(2)] and [animal.selectMany(3)]
```

| Perchance | What it becomes |
|---|---|
| A line without indentation | A section; `title` names the tables and is not a section. |
| A line indented by a tab or two spaces | An entry of the section above. |
| A section whose entries are plain text | A list, each entry with its weight. |
| Any other section, and the one drawn first — `output`, or the first one | A generator, each entry a template with its weight. |
| `^2`, `^0.5` at the end of an entry or of a part of a choice | Its weight: a positive number of at most `MAX_WEIGHT`; the weights of a section or a choice are scaled to whole numbers in the same proportions, three digits after the point kept. |
| `[name]` | `pick('name')` or `generate('name')`. |
| `[name.selectUnique(3)]` | Three different entries, joined by commas. |
| `[name.selectMany(3)]` | Three entries that may repeat, joined by commas. |
| `{a\|b^2\|c}` | One part, chosen by weight; parts may hold references and choices, nested `MAX_DEPTH` deep at most. |
| `{1-6}` | `between(1, 6)`. |
| `2d6+1`, `d20` | `roll('2d6+1')`, when the expression rolls on `tilttek/dice`; otherwise text. |
| `//` at the start of a line or after a space | A comment, to the end of the line; `https://` keeps its slashes. |
| `\[`, `\{`, `\|`, `\^` | The character as written. |

Names are compared without regard to case, their spaces read as hyphens: `[Coat Colour]` is the section
`coat-colour`. A Markdown file is read in its fenced blocks marked `perhaps` or `perchance` when it has any, and whole
otherwise.

Refused: an unknown section, a reference to a section without entries, a bracket or a brace that is not closed, any
other expression in brackets — variables, assignments, other methods —, a choice of one part, an empty range, an entry
outside a section or indented by one space, a weight that is not a positive number, a selection of fewer than one or
more than `MAX_SELECT` entries, text that is not UTF-8, and tables of more than `PerchanceImporter::MAX_BYTES` bytes,
`MAX_SECTIONS` sections or `MAX_SECTIONS` entries in a section.
