# Macros

`DiceMacros` keeps macros for a site, a table and a member, and reads the inputs that name them: `$attack + 2` takes the
tree of the macro `attack` as an operand. A name is read as one token — a `$` and the letters and digits written
against it —, never by replacing text, so that a macro `a` leaves `aa` and `2d6a10` alone, and a macro keeps its own
parentheses: `$attack*2` is `(1d20+5)*2`.

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DiceMacros;
use Tilttek\DiceBundle\Application\MacroShelves;
use Tilttek\DiceBundle\Domain\MacroLevel;

$shelves = new MacroShelves(
    site: ResourceReference::of('site', '1'),
    table: ResourceReference::of('table', '7'),
    member: ResourceReference::of('member', '3'),
);

$macros->define($shelves, MacroLevel::Table, 'attack', '1d20+5');
$macros->define($shelves, MacroLevel::Member, 'init', '1d20; $1+2');

$pool = $macros->parse('$attack*2', $shelves);        // (1d20+5)*2
$pool = $macros->parse('$init', $shelves);            // 1d20, $1+2
$pool = $macros->readRolisteam('$attack+1d6s', $shelves);
$dice->roll($pool);
```

## Shelves and levels

`MacroShelves` holds the owners of an input — a site, a table, a member, opaque `ResourceReference`s the application
gives —, any of them left out. A name is looked for on the member's shelf first, then the table's, then the site's:
the member's `attack` hides the table's, which hides the site's.

The names a macro writes are looked for from its own level on: a site's macro names the site's macros only, a table's
those of the table and the site. A site's macro means the same at every table and for every member.

## Defining, removing, erasing

| Method | Does |
|---|---|
| `define($shelves, $level, $name, $expression)` | Keeps the macro for the owner the shelves give at that level, replacing the one of the same name |
| `remove($level, $owner, $name)` | Removes a macro; whether there was one |
| `of($level, $owner)` | The macros kept at a level for an owner, by name |
| `eraseOwner($owner)` | Erases an owner's macros at every level, when the member, the table or the site is erased |
| `parse($input, $shelves, $locale)` | An input in a language's notation, its macros resolved |
| `readRolisteam($input, $shelves)` | A command of Rolisteam's DiceParser, its macros resolved |

A name is a letter, then letters and digits, 32 at most, kept in lower case. An expression is written in the library's
notation, and is kept only when it parses with the macros of its level and those after it: a macro that names a macro
that is not there, or names itself through others, is refused. A macro of one instruction is an operand anywhere an
operand is; a macro of several instructions is the whole input, written alone. A macro removed later makes the macros
that name it refused, with the name that is missing.

## Limits

An input expands 32 macros at most, the macros of a macro counted each time it is expanded: past that, `LimitExceeded`.
The pool an input gives is checked against the library's limits once its macros are in it, as any pool is. Each macro
is read once per input, and nothing is kept from one input to the next: `DiceMacros` holds no state between requests.

## The table

The macros are kept in the table `tilttek_dice_macro`, or in memory with `store: memory`; see
[installation](installation.md#the-macros-table).
