# Systems and records

A `GameSystem` is what the other tools read of a game system: its key, its names, its version, its own provenance,
and six lists of records in the order the system gives them.

| Record | What it holds | For instance |
|---|---|---|
| `Attribute` | A key, names, abbreviations, descriptions, and the lowest and highest values when the system has some | `str`, Strength, `STR`, 1 to 30 |
| `DiceExpression` | A key, names, and a roll in the canonical notation of `tilttek/dice` | `advantage`, Advantage, `2d20kh1` |
| `Term` | A key, names, descriptions, and a category | `condition.blinded`, Blinded, `condition` |
| `RuleVariant` | A key, names and descriptions: an optional rule a table may choose to play with | `encumbrance`, Encumbrance |
| `Difficulty` | A key, names, descriptions, a method, bands from the easiest and tables of whole numbers | `check`, Typical Difficulty Classes, `easy` 10 … `nearly-impossible` 30 |
| `PartyRole` | A key, names and descriptions: a role the members of a party fill | `bearer`, Lantern bearer |

Every record carries its `Provenance`, its own or the system's ([Sources and attribution](attribution.md)).

## Keys

A key is 1 to 96 lowercase ASCII letters, digits, `_` and `-`, with single dots between them: `str`,
`condition.blinded`, `sleight-of-hand`. A system refuses two records of the same kind and key; records of two kinds —
an attribute and a term — may share one. A category, a method and the key of a band are keys too.

## Texts by locale

Names, abbreviations and descriptions are `Translations`: a text by locale, at most 64 locales, each text at most
4 000 characters of UTF-8 without control characters other than line breaks and tabs. A locale is written `fr`,
`fr_CA`, `fr-ca` or `es-419`, and kept as `fr`, `fr_CA` or `es_419`.

`in()` finds a text for a reader: the locale itself, then its language, then the fallback locale — English unless
another is given —, then the first text held.

```php
$names = Translations::of(['en' => 'Strength', 'fr' => 'Force']);

$names->in('fr_CA');        // "Force", from its language
$names->in('de');           // "Strength", the fallback
$names->in('de', 'fr');     // "Force", another fallback
$names->with('es', 'Fuerza');
```

A record has at least one name; abbreviations and descriptions may be empty.

## Attributes and their bounds

`minimum` and `maximum` are the values the system allows, either one or both null when it sets none. `allows()` says
whether a value lies within them.

```php
$strength = $system->attribute('str');

$strength->allows(31);   // false, for a system whose scores go from 1 to 30
```

## Dice expressions

A dice expression is read by `tilttek/dice` when it is made: a notation the library cannot read is refused, and the
expression keeps the canonical form the library writes back — ` 2D20KH1 ` is kept as `2d20kh1`. `pool()` gives the
expression read, ready to roll with `DiceRoller::roll()`; `notationIn()` writes it in the notation of a language,
`2W20kh1` in German.

```php
$roll = new DiceRoller()->roll($system->dice('advantage')->pool(), new Randomizer());
```

## Difficulty numbers

`Difficulty` holds the numbers a system gives for one purpose. Its `bands` go from the easiest, each a
`DifficultyBand` with a key, names and, when the band stands for a single number, that number: the class of a check of
that difficulty, or what the difficulty adds to a number found elsewhere. Its `numbers` are tables of whole numbers by
label — a level `1`, a challenge `1/8`, a difference `-4`, a name `party-size` —, nested three levels at most. The
`method` says how a tool reads them; the library keeps them as they are given and checks only their shape and bounds.

```php
$check = $system->difficulty('check');

$check->bandKeys();                     // ['very-easy', 'easy', 'medium', 'hard', 'very-hard', 'nearly-impossible']
$check->band('hard')?->number;          // 20

$encounter = $system->difficulty('encounter');

$encounter->method;                     // 'budget'
$encounter->number('budgets', 1, 'low'); // 50, the XP budget of a level-1 character for a low difficulty
$encounter->number('costs', '1/8');     // 25
```

`number()` follows the labels given and returns a number, a table, or null when a label is missing. The import profiles
read these methods into their numbers:

| Method | Bands | Tables |
|---|---|---|
| `check` | Each band's number is the class of a check | None |
| `level-dc` | Each band's number is what the difficulty adds to the class of a level | `levels`: the class of each level |
| `budget` | The bands of an encounter, from the easiest | `budgets`: by a character's level, an amount for each band; `costs`: by an adversary's challenge, its cost; `multipliers` (optional): by the number of adversaries, a percent |
| `level` | The bands of an encounter, from the easiest | `party-size`: the standard party; `budgets` and `adjustments`: by band, for that party and for each character over or under it; `costs`: by an adversary's level against the party's |

A record holds up to 24 bands and 10 000 numbers, each within ±1 000 000 000; a label is 1 to 32 lowercase letters,
digits, `+`, `/`, `.`, `_` or `-`. A tool with numbers of its own reads them under a method of its own.

## Looking records up

| Method | What it gives |
|---|---|
| `attribute()`, `dice()`, `term()`, `variant()`, `difficulty()`, `partyRole()` | The record of a key, or `UnknownRecord` |
| `hasAttribute()`, `hasDice()`, `hasTerm()`, `hasVariant()`, `hasDifficulty()`, `hasPartyRole()` | Whether the system holds a key |
| `attributes()`, `diceExpressions()`, `variants()`, `difficulties()`, `partyRoles()` | Every record of the kind, in order |
| `terms()` | Every term, or those of one category, in order |
| `categories()` | The categories of the terms, in the order they first appear |
| `name()` | The system's name in a locale |
| `provenances()` | Every source the system draws from, each once |

`Systems` holds the systems of an application by key: `get()`, `has()`, `all()`, and `with()` to add a system or to
replace one of the same key. `SystemReader::load()` gives one ([The format](format.md)).

A system holds at most 10 000 records, of every kind together. Every lookup by key reads an
index built when the system is made.

## Localisations

A `Localisation` holds the texts of one more language — the system's name, and texts of attributes, dice expressions,
terms, rule variants, difficulty numbers with the names of their bands, and party roles by key — with the provenance of
the translation. `GameSystem::localised()` gives the system with those texts laid over its own; a record the
localisation does not name keeps its texts, and a key the system does not hold — a band of difficulty included — is
refused with `UnknownRecord`. The translation's provenance is kept by locale in `translationSources`, and shown by the
attribution of a page in that language.

```php
$spanish = $system->localised(new Localisation(
    'es',
    new Provenance('Spanish translation', Licence::CcBy40, 'Translated by the volunteers of the site, CC BY 4.0.'),
    'Linternas en la oscuridad',
    attributes: ['grit' => ['name' => 'Temple', 'abbreviation' => 'TMP']],
    dice: ['action' => 'Tirada de acción'],
    terms: ['condition.shaken' => ['name' => 'Conmocionado']],
));
```

A localisation is usually a document of its own, read by `SystemReader` ([The format](format.md#localisations)).
