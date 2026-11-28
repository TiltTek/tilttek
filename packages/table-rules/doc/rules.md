# Rules, versions and acceptance

## Rules

A `Rule` is one house rule: an identifier, a title, a text and an optional category.

- The **identifier** stays the same from one version to the next: it is how the comparison follows a rule whose title
  or text changed. A new identifier is a new rule.
- The **title** is one line of 1 to 200 characters.
- The **text** is plain text of 1 to 10,000 characters on up to 100 lines. Its line breaks become line feeds, each line
  loses its trailing blanks, and the blank lines before and after it are removed; the indent of the lines is kept. The
  application renders it as it likes — as Markdown, as paragraphs — and escapes it when it does.
- The **category** is an identifier the application names: `safety`, `table-talk`, `dice`.

Titles, texts and notes are valid UTF-8 without control characters, except the tab and the line feed, and without the
characters that reorder the text shown (bidirectional embeddings, overrides and isolates). Lengths count code points.

Every identifier — table, player, rule, category, variant, system, author — is 1 to 128 letters, digits, `_`, `.`, `:`
or `-`, starting with a letter. It holds no `@`: a player is an identifier of the application, never an address.

## Versions

`TableRules::publish()` publishes the next `RulebookVersion` of a table, which holds from then on:

| Value | What it is |
|---|---|
| `number` | 1 for the first version, then one more for each, up to 10,000. |
| `rules` | Up to 200 rules in the order the table reads them, each identifier once, their texts 100,000 characters at most together. |
| `variants` | Up to 100 identifiers of the rule variants of the game system the table plays with, each once, kept sorted. |
| `system` | The identifier of the game system the variants belong to, or null. |
| `note` | What changed, for the players: up to 2,000 characters on 50 lines; an empty note is none. |
| `publishedBy`, `publishedAt` | Who published it, and when, from the clock. |
| `requiresAcceptance` | Whether every player answers this version anew. |

A version holds at least one rule or one variant. A version whose rules, in the same order, variants and system are
those that hold is refused with `RulesRefused`: the note, the author and the moment do not make a new version. A
published version is never changed; to change the rules is to publish the next one.

## Variants

A variant is an option of the game system the table chooses: a critical hit rule, flanking, a gritty rest. The library
keeps their identifiers and compares them between versions; what a variant does is the application's, and the other
parts of the application read the variants of the version that holds.

Without a catalogue, any identifier is a variant. Given a `Contract\VariantCatalogue`, `publish()` checks each variant
against the system of the version, and refuses one the system does not offer with `InvalidRules`.

`Catalogue\SystemVariantCatalogue` is the catalogue of the rule variants of `tilttek/rpg-systems`: a variant is one
its system holds, by key — `encumbrance` for the SRD 5.1, `free-archetype` for Pathfinder Second Edition, as their
import profiles state them. A version that names no system, or a system the application does not offer, chooses no
variant.

```php
use Tilttek\TableRules\Catalogue\SystemVariantCatalogue;

$tableRules = new TableRules($store, $clock, variants: new SystemVariantCatalogue($systems));
$tableRules->publish('table-7', 'gm-1', $rules, variants: ['encumbrance'], system: 'srd-5.1');
```

## Acceptance

A player answers the version that holds with `accept()` or `decline()`, giving the number of the version they read.
When another version was published since, the answer is refused with `RulesRefused`: the player reads the new one
first. Being at the table accepts nothing: a player who never answered is pending.

The `Rulebook` keeps the last decision of each player — the version, accepted or declined, and when — for up to 500
players. Answering again what the player already answered changes nothing and tells no event; a player who declined
may accept later, and the other way round. `forget()` removes the decision of a player who left the table or asked to
be forgotten.

## Re-acceptance

A version published with `requiresAcceptance` — the default — asks every player again. One published without it — a
typo fixed, a sentence made clearer — keeps the decisions: an acceptance of a version since the last one that required
acceptance still holds. The first version always requires acceptance.

The rulebook keeps the number of the last version that required acceptance as its `baseline`, and a player's
`AcceptanceState` follows from it:

| State | When |
|---|---|
| `Pending` | No rules are published, the player never answered, or the player declined a version before the baseline. |
| `Accepted` | The player accepted the baseline or a later version. |
| `Declined` | The player declined the baseline or a later version. |
| `Outdated` | The player accepted a version before the baseline: the rules changed since. |

A player who is pending or outdated has the rules that hold to answer: `PlayerStatus::mustAnswer()`.

## What changed

`Diff\RulebookDiffer` compares two versions, in either order, and gives a `Diff\RulebookDiff`:

- the rules only in the later version (`added`), and only in the earlier one (`removed`);
- the rules of both whose title, text or category changed (`changed`), each a `Diff\RuleChange` with the rule before
  and after, and the lines of its text, kept, removed or added, the removed lines before the added ones where a passage
  changed;
- the rules of both read in another order (`moved`): the fewest rules that, moved, give the new order;
- the variants chosen and dropped, and the system before and after.

`TableRules::diff()` compares two versions of a table by their numbers. The rules are matched by identifier, and the
lines and the order by the longest common subsequence; the work is bounded by the bounds of a version.

## The views

`TableRules::status()` gives a `View\PlayerStatus`: the player's state, the version that holds, the version the player
last answered, the decision and its moment, and what changed from the version answered to the one that holds — null
when the player answered none, or answered the one that holds.

`TableRules::roster()` gives a `View\RosterView` for up to 500 players the application names, each once — the rulebook
knows only those who answered: each player's status in the order given, how many are in each state, and whether every
player accepted the rules that hold. Players who answered the same version share one comparison.

Both views, the versions and the comparison give their plain data with `toArray()`, the moments in the ATOM format, to
send to the table as JSON.
