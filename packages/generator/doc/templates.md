# Templates

A template is a Twig template rendered as text, without escaping. Its output has its runs of spaces and line breaks
made into one space and is trimmed, so that a template may be written on several lines and optional parts leave no
double space.

## Functions

| Function | What it gives |
|---|---|
| `pick('list')` | One entry, chosen by weight. |
| `pick('list', 2)` | Two entries without duplicates — by text — or fewer when the list does not have enough. |
| `pick('list', where: {gender: 'f'})` | An entry whose tags match: each tag asked for has one of the values asked for (`{kind: ['bow', 'tool']}`). |
| `pick('list', not: x)` | An entry whose text is not excluded: a text, an entry, or a list of them, even nested. |
| `generate('name')` | Another generator's result. |
| `generate('name', 3)` | Three different results of another generator, or fewer when it cannot find enough. |
| `generate('story', with: {hero: name})` | Another generator's result, whose templates see the values given as variables. |
| `roll('2d6+1')` | The total of a roll of `tilttek/dice`, with the draw's randomness; the first total of a pool. |
| `between(1, 100)` | A whole number from the first to the second, both included, with the draw's randomness. |

An entry prints its text; `entry.price` reads a field and `entry.tags.kind` a tag. A single pick that finds no entry
left once its filters and exclusions are applied fails with `ExhaustedList`; a pick of several returns what it found.
A single choice — one template, one candidate — takes no randomness. `roll` and `between` count as picks.

```twig
{% set first = pick('name.syllable') %}{{ first }}{{ pick('name.syllable', not: first) }}
```

## Variables

`{% set %}` keeps a value for the rest of the template; the generator's `vars` keep a value for the whole result, in all
its templates and fields. `generate(…, with: {…})` gives values to the generator it calls, as variables of its
variables, body and fields: their names are a letter then letters, digits or `_`, never `loop`, `state` or `previous`. A
structured result's fields are variables of the later fields. A generator with a state reads it as `state` and the state
before the result as `previous`; see [Tables with a memory](memory.md).

## Filters

The tags `set`, `if`, `for` and `apply`; the tests `defined`, `empty`, `null`, `none`, `even`, `odd`, `divisible by`,
`same as` and `iterable`; Twig's filters `capitalize`, `title`, `upper`, `lower`, `trim`, `replace`, `slice`, `join`,
`first`, `last`, `length`, `default`, `abs` and `round` — `replace` and `join` bounded by the limit of bytes (see
below) —; and the generator's own:

| Filter | What it does |
|---|---|
| `upper_first` | A capital first letter, the rest untouched (`capitalize` lowers the rest). |
| `elide` | The elisions and contractions of the language over the whole text; a word written with `^` before it is kept as written ([Grammar](grammar.md#keeping-a-word-as-written)). |
| `article(kind, gender, plural)` | The word with its article: `definite`, `indefinite`, `partitive` or `demonstrative`. |
| `agree(with, plural)` | An adjective in the gender and number of a noun entry, or of those given. |
| `plural(count)` | The plural of a word, or the word itself when the count takes the singular. |

See [Grammar](grammar.md).

## The sandbox

Templates run in Twig's sandbox, with only the tags, filters, functions and tests above, and see no object but entries,
whose text and fields only they read. The range operator `..` is refused, since it could build an array of any size;
loops go over lists that picks made, which the limit of picks bounds.

## The budget of a result

The sandbox bounds what a template reaches; the limits bound what it costs, inside the template as well as between
templates:

- each turn of a `for` loop and each `{% set %}` is a step, counted against `Limits::$steps`; at each step the memory
  the result has grown by is checked against `Limits::$megabytes`, and its time and the draw's against their limits;
- each pick, roll, `between` and generator called counts against `Limits::$picks` and checks the times too;
- `replace` and `join` refuse to make a text longer than `Limits::$bytes`, measured before the text is made: `replace`
  from the occurrences of each search in the text and the length its replacement adds, `join` from its parts and its
  glues;
- the range operator `..` is refused, and loops go over the lists that picks made.

A loop that never picks stops at its step limit; a text that doubles at each assignment stops at the step that
crosses the memory limit. Past any of them the draw fails with `LimitExceeded`. Twig's sandbox, a dedicated Twig
environment and these limits together let an application load templates written by others — its members, or the
authors of an imported grammar — and draw from them within a bounded cost; the [checks](#the-checks) still run on
every template before any draw.

## The checks

The checks run when the `Generator` is built, over every template, variable, field, cell and value of a state:

- the template compiles;
- it uses nothing outside the sandbox's lists;
- every list, tag and generator it names in plain words exists — `pick('a.list', where: {gender: 'f'})`,
  `generate('npc')`; a name given by a variable is checked when it is drawn;
- every generator a field or a cell names exists, a graph's dice expression parses, and the generator's language and
  filters exist.

Every problem is listed in one `InvalidCatalog`, each with its template: `inn:field:menu: the generator "dish" does not
exist`.
