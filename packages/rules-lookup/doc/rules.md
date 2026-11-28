# Rules

## A rule

```php
$rule = new Rule(
    new RuleId('open-rules', 'combat.cover', '1.0'),
    Translations::of(['en' => 'Cover', 'fr' => 'Abri']),
    $provenance,
    Translations::of(['en' => 'Walls give cover against ranged attacks.']),
    'combat',
    ['condition.blinded'],
);

$rule->title('fr_CA');   // "Abri"
$rule->text('fr');       // the English text: the rule has no French one
```

| Field | What it holds |
|---|---|
| `id` | Its `RuleId`: system, key and version |
| `titles` | Its title by locale, required |
| `provenance` | Its source: title, licence, attribution, author and address, a `Provenance` of `tilttek/rpg-systems` |
| `texts` | Its text by locale, possibly none |
| `category` | Its kind, a key: `condition`, `combat` |
| `references` | The keys of the rules it refers to, in the same system and version; at most 64, never itself, each once |

Titles and texts are `Translations` of `tilttek/rpg-systems`, read for a locale as they are there: the locale, its
language, English, then the first text. A text holds at most 4 000 characters: a long section of a source is split
into several rules that refer to one another.

## Identifiers

`RuleId` names a rule by its system, its key and the version of the system:

```php
$id = new RuleId('srd-5.2', 'condition.blinded', '5.2.1');

$id->toString();                                 // "srd-5.2@5.2.1:condition.blinded"
RuleId::fromString('lanterns:range.near');       // a system without version
$id->sibling('condition.prone');                 // another rule of the same system and version
```

The system and the key are keys of `tilttek/rpg-systems`: lowercase ASCII letters, digits, `_`, `-` and single dots
between them, 96 characters at most. A version is 1 to 32 letters, digits, `.`, `_`, `+` or `-`, starting with a letter
or a digit. Two versions of a system are two sets of rules: a search narrowed to a version reads only its rules.

## The rules of a system

`RulesFromSystem` gives the rules of a system of `tilttek/rpg-systems`, read from a document of its format or from a
data package:

| Record | Rule key | Category | Text |
|---|---|---|---|
| A term, `condition.blinded` | `condition.blinded` | The term's category | Its descriptions |
| An attribute, `grit` | `attribute.grit` | `attribute` | Its descriptions |
| A dice expression, `advantage` | `dice.advantage` | `dice` | Its notation in each locale of its names, `2d20kh1`, `2W20kh1` in German |

Each rule keeps the record's provenance, and the system's key and version.

## References

A rule refers to other rules of its system and version by their keys. `RulesLookup::references()` follows them —
a reference the store does not hold is left out — and `RulesLookup::referencedBy()` gives the rules that refer to one:

```php
$lookup->references(RuleId::fromString('open-rules@1.0:combat.cover'), 'fr');
$lookup->referencedBy(RuleId::fromString('open-rules@1.0:condition.blinded'));
```
