# Creation rules

`CreationRules` say how a system makes a random character: the key of the rules, the key of the system in
`tilttek/rpg-systems`, the `Stats` that fill its attributes, and the `Field`s rolled after them, in order. Rules have
stats, fields, or both.

```php
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

$rules = new CreationRules('lanterns.wanderer', 'lanterns', new Stats(StatMethod::RolledArray, ['grit', 'wits', 'glow'], '1d4+1'), [
    Field::generated('lineage', 'lanterns.lineage'),
    Field::generated('name', 'lanterns.name.{lineage}'),
    Field::generated('traits', 'lanterns.trait', count: 2, unique: true),
    Field::rolled('age', '2d10+14'),
]);
```

Keys of rules, systems and attributes are written as `tilttek/rpg-systems` writes them: 1 to 96 lowercase ASCII letters,
digits, `_`, `-` and single dots between them. Rules hold at most 64 fields, each key once.

## Stats

`Stats` name the attributes they fill, 1 to 32, each once, in the order the character lists them, and how:

| `StatMethod` | Written | The values |
|---|---|---|
| `Each` | `each` | The roll is made for each attribute in turn, in the order of the rules: 3d6 in order. |
| `RolledArray` | `rolled-array` | The roll is made once per attribute, and the values are dealt to the attributes at random: 4d6 drop the lowest, arranged by luck. |
| `Array` | `array` | The fixed values, one per attribute, are dealt to the attributes at random: a standard array. |

The two rolled methods take a roll in any notation `tilttek/dice` reads — `3d6`, `4d6kh3`, `1d4+1` — kept in its
canonical form, `Stats::$roll`; they take no fixed values. A fixed array takes no roll and one value per attribute,
each from -1,000,000 to 1,000,000. A roll must give a number: a roll whose first instruction gives a text is refused when
it is made.

The bounds belong to the system: an attribute of `tilttek/rpg-systems` may have a minimum and a maximum, and a value
outside them is refused — when the rules are checked for a fixed array, whose every value must fit every attribute,
and when the character is rolled for the other methods.

## Fields

A `Field` is a value of the character besides its attributes, under a key of a lowercase ASCII letter followed by up to
63 lowercase letters, digits or `_`. It gives one value, or a list of 2 to 16 when its `count` asks for more.

- `Field::generated($key, $generator, $count = 1, $unique = false)` draws from a generator of `tilttek/generator`. One
  result is a text, or an array for a generator that gives structured results; several results are a list. A unique
  field asks the generator for different results, and the roll fails when it finds too few.
- `Field::rolled($key, $notation, $count = 1)` rolls dice: one roll is an integer, several are a list of integers.

### Generators named by earlier fields

The name of a field's generator may hold placeholders, each replaced by the value of a field before it when the
character is rolled:

| Placeholder | Reads |
|---|---|
| `{lineage}` | The value of the field `lineage`: a text, or an integer written in digits. |
| `{lineage.key}` | The value `key` of the structured result the field `lineage` drew. |

`names.{lineage}` draws from `names.river` when the lineage came out `river`. A placeholder names a field before the one
that holds it, and a field that gives a single value; anything else is refused when the rules are built. Once replaced,
the name must be a name of a generator — letters, digits, `_` and `-` in parts joined by dots — and a generator of the
catalogue; a value that gives no text, holds a space, or names a generator that does not exist makes the roll fail.
Write the generator a placeholder reads so that it gives keys, `river`, or structured results with a key, rather than
the text a player reads.

## The catalogue

`RulesCatalogue` holds up to 1,000 rules by key, each key once: `get()`, `has()`, `all()` in the order given, and
`forSystem()` for the rules of one system. It is an immutable value that requests share.
