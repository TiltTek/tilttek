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
| `roll('2d6+1')` | The total of a roll of `tilttek/dice`, with the draw's randomness; the first total of a pool. |

An entry prints its text; `entry.price` reads a field and `entry.tags.kind` a tag. A single pick that finds no entry
left once its filters and exclusions are applied fails with `ExhaustedList`; a pick of several returns what it found.
A single choice — one template, one candidate — takes no randomness.

```twig
{% set first = pick('name.syllable') %}{{ first }}{{ pick('name.syllable', not: first) }}
```

## Variables

`{% set %}` keeps a value for the rest of the template; the generator's `vars` keep a value for the whole result, in all
its templates and fields. A structured result's fields are variables of the later fields. A generator with a state reads
it as `state` and the state before the result as `previous`; see [Tables with a memory](memory.md).

## Filters

The tags `set`, `if`, `for` and `apply`; the tests `defined`, `empty`, `null`, `none`, `even`, `odd`, `divisible by`,
`same as` and `iterable`; Twig's filters `capitalize`, `title`, `upper`, `lower`, `trim`, `replace`, `slice`, `join`,
`first`, `last`, `length`, `default`, `abs` and `round`; and the generator's own:

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

The sandbox bounds what a template reaches, not what it costs within one render: the time limits are checked at each
pick, roll and generator called and before each template is rendered, never in the middle of a loop, and a text grown
in a loop by `~` or `replace` grows until the render ends or the memory runs out. Templates are code the application
trusts, written by its developers or its administrators; a template from anyone else is reviewed before it is loaded.

The checks run when the `Generator` is built, over every template, variable, field, cell and value of a state:

- the template compiles;
- it uses nothing outside the sandbox's lists;
- every list, tag and generator it names in plain words exists — `pick('a.list', where: {gender: 'f'})`,
  `generate('npc')`; a name given by a variable is checked when it is drawn;
- every generator a field or a cell names exists, a graph's dice expression parses, and the generator's language and
  filters exist.

Every problem is listed in one `InvalidCatalog`, each with its template: `inn:field:menu: the generator "dish" does not
exist`.
