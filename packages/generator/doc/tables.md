# Random tables

The `tables/` directory of the package holds random tables ready to draw, or to copy and adapt: the generators most
games ask for, written with the library's own syntax. Their content is neutral, tied to no game. Each table is written
in English, in `tables/en/`, and in French, in `tables/fr/`. `Tables::directory()` gives the directory of the English
tables and `Tables::directory('fr')` that of the French ones, `Tables::file('shop', 'fr')` the file of one table,
`Tables::NAMES` their names and `Tables::LOCALES` their languages; another language is an `InvalidDefinition`:

```php
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\Generator\Tables;

$generator = new Generator(new FileLoader()->catalog([Tables::directory('fr'), 'generators']));
$generator->draw('table.shop')->first();
```

The two versions of a table have the same shape: the same lists, generators, state values, variables and fields, under
the same names. An application loads one language or the other — never both, since their names are the same — and
keeps its states when it changes language: the values the application sets keep their identifiers in French too
(`terrain: forest`, `odds: likely`, `chaos: 6`), except the place of the rumours, a text written with its article
(`le village`). The French tables are not translated word for word: they are written in French, with the grammar
filters of the language — the agreement of the creatures, the elision of the hooks and rumours, the article of a shop's
sign.

Each table is one file whose lists and generators are named `table.<name>` and `table.<name>.…`, and names nothing
outside its file: a file may be loaded alone, or copied under another name and adapted. Loading a table and an
application's copy of it together makes two lists of the same name, which the catalog refuses: an application takes the
table, or its copy.

| Table | Generators | What they give |
|---|---|---|
| `encounter` | `table.encounter` | Who the party meets, how many, what they are doing, how far away and how they take it. The terrain — `forest`, `road`, `hills`, `swamp`, `ruins` or `underground` — is a state value the application sets; `road` at the first draw. |
| `magic-item` | `table.magic-item` | A structured magic item: its name and power, its appearance, a quirk and its charges. |
| `loot` | `table.loot`, `table.loot.unidentified` | Coins, gems with their value, a work of art, and an unidentified item: what it looks like, what it really is, and a clue that points to it. |
| `shop` | `table.shop`, `table.shop.merchant` | A shop: its kind and sign, its merchant with a trait and a mood, and five items of its stock with their price in silver. |
| `hook` | `table.hook` | An adventure hook: who asks, what for, where, why, and the twist the party does not know yet. |
| `rumour` | `table.rumour` | A rumour about a place and how true it is. The place is a state value the application sets; `the village` at the first draw. |
| `oracle` | `table.oracle`, `table.oracle.yes-no`, `table.oracle.inspiration` | A solo play oracle: a yes-or-no answer weighed by the odds of the question and a chaos factor, with a random event on a double; a plain yes-or-no answer; an action and a theme for inspiration. |
| `fortune` | `table.fortune`, `table.fortune.day`, `table.fortune.prophecy` | Fortunes: a spread of three cards from a deck of twenty-two, each upright or reversed; a card of the day; a prophecy from three tables. |

## The solo play oracle

`table.oracle` keeps two state values the application sets between questions: `odds`, one of `certain`, `likely`,
`even`, `unlikely` and `impossible`, and `chaos`, the chaos factor from 1 to 9. The chance of a yes is the chance of the
odds — 90, 70, 50, 30 or 10 in a hundred — moved by 5 for each step of chaos above or below 5, and kept from 1 to 99; a
roll of a fifth of the chance or under is an exceptional yes, a roll in the last fifth of the rest an exceptional no.
A double — 11, 22, … 99 — whose tens are at most the chaos factor brings a random event. The result is structured:

```php
$generator->draw('table.oracle', new Draw(state: ['odds' => 'likely', 'chaos' => '6']))->first();
// ['roll' => '34', 'answer' => 'Yes', 'event' => '']
```

The arithmetic stays in whole numbers (`//`), so that the same seed gives the same answer everywhere.

## Writing a table

The tables use the library's techniques, which an application's own tables may copy: an entry chosen by a variable
through a tag (`table.loot.unidentified` draws a number, then picks the appearance and the identity tagged with it, so
that they match), the type of a shop drawn in a variable and passed to the picks of its stock as a tag, a state value as
an input the application gives, and loops over the entries of a pick of several, separated by `loop.index0`.
