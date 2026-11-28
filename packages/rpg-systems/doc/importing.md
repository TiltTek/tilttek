# Importing open sources

`Importer` turns an extract of an open source into a system of the library's format. An extract is a mapping of table
names to lists of records, in JSON or YAML; an `ImportProfile` says which tables to read, which fields hold what, and
which licences the source may be under. The caller states the source — its title, its licence and the attribution it
asks for —, and every record of the system carries it.

```php
$srd = new Provenance(
    'System Reference Document 5.2',
    Licence::CcBy40,
    'The attribution statement the SRD 5.2 asks for, copied from the document itself.',
    'Its publisher',
    'https://the-address-of-the-srd.example',
);

$system = new Importer()->importText(ImportProfile::srd52(), $json, Syntax::Json, $srd);

file_put_contents('systems/srd-5.2.json', new SystemWriter()->toJson($system));
```

The import runs once, when the extract changes; the application then reads the system written in the format, or a
[data package](data-packages.md) that carries it. The
library ships no extract: the application obtains the source's data and checks its licence, and the importer refuses
a source whose licence is not one the profile accepts.

## The SRD 5.2 profile

`ImportProfile::srd52()` makes the system `srd-5.2`, version `5.2`, from material under `CC-BY-4.0`:

| Table | Fields read | Gives |
|---|---|---|
| `ability-scores` | `index`, `full_name` the name, `name` the abbreviation, `desc` | Attributes from 1 to 30 |
| `skills` | `index`, `name`, `desc` | Terms `skill.…` |
| `conditions` | `index`, `name`, `desc` | Terms `condition.…` |
| `damage-types` | `index`, `name`, `desc` | Terms `damage-type.…` |
| `magic-schools` | `index`, `name`, `desc` | Terms `magic-school.…` |

It adds three dice expressions: `d20-test` `1d20`, `advantage` `2d20kh1` and `disadvantage` `2d20kl1`, and two sets of
difficulty numbers, as the source gives them:

| Key | Method | Holds |
|---|---|---|
| `check` | `check` | The typical difficulty classes: `very-easy` 5, `easy` 10, `medium` 15, `hard` 20, `very-hard` 25, `nearly-impossible` 30 |
| `encounter` | `budget` | The bands `low`, `moderate` and `high`; `budgets`, the XP budget of a character by level from 1 to 20; `costs`, the XP of a creature by challenge rating from 0 to 30 — 10 for challenge 0 |

The source names no rule variant and declares no party role.

## The SRD 5.1 profile

`ImportProfile::srd51()` makes the system `srd-5.1`, version `5.1`, from material under `CC-BY-4.0`. It reads the same
tables as the SRD 5.2 profile, with the same fields, and adds three dice expressions: `d20-roll` `1d20`, `advantage`
`2d20kh1` and `disadvantage` `2d20kl1`, two rule variants — `encumbrance` and `skills-with-different-abilities` — and
the difficulty numbers `check`, the typical difficulty classes of the SRD 5.2 profile. The source gives no rules of
encounter difficulty and declares no party role.

## The Pathfinder Second Edition profile

`ImportProfile::pathfinder2e()` makes the system `pf2e` from material under `ORC`:

| Table | Fields read | Gives |
|---|---|---|
| `attributes` | `slug`, `name`, `abbreviation`, `description` | Attributes, without bounds |
| `skills` | `slug`, `name`, `description` | Terms `skill.…` |
| `conditions` | `slug`, `name`, `description` | Terms `condition.…` |
| `traits` | `slug`, `name`, `description` | Terms `trait.…` |

It adds three dice expressions: `check` `1d20`, `fortune` `2d20kh1` and `misfortune` `2d20kl1`, four rule variants —
`automatic-bonus-progression`, `dual-class`, `free-archetype` and `proficiency-without-level` — and three sets of
difficulty numbers:

| Key | Method | Holds |
|---|---|---|
| `simple` | `check` | The simple DCs by proficiency rank: `untrained` 10, `trained` 15, `expert` 20, `master` 30, `legendary` 40 |
| `level` | `level-dc` | `levels`, the DC of each level from 0 to 25; each band's number the adjustment of a difficulty, from `incredibly-easy` −10 to `incredibly-hard` +10 |
| `encounter` | `level` | The bands `trivial` to `extreme`; `party-size` 4; `budgets` 40 to 160 and `adjustments` 10 to 40 by band; `costs`, the XP of a creature from four levels under the party (10) to four above (160) |

The source declares no party role.

## The Fate Core profile

`ImportProfile::fateCore()` makes the system `fate-core` from material under `CC-BY-3.0`. Fate has no attributes:

| Table | Fields read | Gives |
|---|---|---|
| `skills` | `key`, `name`, `description` | Terms `skill.…` |
| `approaches` | `key`, `name`, `description` | Terms `approach.…`, the approaches of Fate Accelerated |
| `ladder` | `key`, `name`, `description` | Terms `ladder.…`, the steps of the ladder, `Good` described `+3` |

It adds one dice expression: `fate-roll` `4dF`.

## The version of the source

A profile gives the system the version it names — `5.1`, `5.2` —, or none. `withVersion()` gives the same profile with
the version of the files an extract is taken from, so that the system, and the data package written from it, name the
version they hold:

```php
$system = new Importer()->importText(ImportProfile::srd52()->withVersion('5.2.1'), $json, Syntax::Json, $srd);

$system->version; // "5.2.1"
```

A version is a text of at most 4 000 characters without control characters; an empty one is refused with an
`InvalidRecord`.

## How a record is read

- The tables a profile does not name are left out; a table the extract lacks gives no record.
- A key is lowercased, and every run of characters a key cannot hold becomes a hyphen: `Sleight of Hand` gives
  `sleight-of-hand`. A term's key is its category, a dot, and that key.
- A description is a text, or a list of paragraphs joined by a blank line.
- A record that cannot be read is refused with its place: `conditions[0].name: a text is expected.`

## A profile of your own

An extract shaped otherwise, or another open source, is read by a profile of the application's own:

```php
$profile = new ImportProfile(
    'my-system',
    'Mon système',
    [Licence::CcBy40, Licence::CcBySa40],
    attributes: new TableMapping('caracteristiques', key: 'id', name: 'nom', abbreviation: 'abrege'),
    terms: [new TableMapping('etats', 'condition', key: 'id', name: 'nom', description: 'texte')],
    dice: ['test' => '1d20'],
    diceNames: ['test' => 'Test'],
    locale: 'fr',
    attributeMinimum: 1,
    attributeMaximum: 20,
    variants: ['nuit-longue' => 'La longue nuit'],
    difficulties: [
        ['key' => 'jet', 'name' => 'Difficulté', 'method' => 'check', 'bands' => [
            ['key' => 'facile', 'name' => 'Facile', 'number' => 8],
            ['key' => 'difficile', 'name' => 'Difficile', 'number' => 12],
        ]],
    ],
    partyRoles: ['guide' => 'Guide'],
);
```

`variants`, `difficulties` and `partyRoles` are the records the source states once for all, as `dice` is: each record
carries the source the caller states, and is named in the profile's locale. A set of difficulty numbers takes `key`,
`name`, and the optional `description`, `method`, `bands` — each `key`, `name` and an optional `number` — and `numbers`.

A `TableMapping` names a table, the category of its terms — none for attributes, `term` when a table of terms gives
none —, and the fields of the key, the name, the abbreviation and the description.
