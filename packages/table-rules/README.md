# tilttek/table-rules

**Keep a table's house rules versioned, accepted by each player and re-accepted when they change, with what changed
between versions; system-neutral.**

tilttek/table-rules keeps the house rules of a table or a campaign — no phones at the table, how the dice are rolled,
what happens when a player is absent — and the rule variants of the game system the table plays with. A rule is a
title and a text; a variant is an identifier, checked against the rule variants of the `tilttek/rpg-systems` systems,
or against a catalogue of the application's own, or taken as it is given.

- **Versions.** Each publication is a new numbered version that holds from then on, never changed afterwards, with a
  note on what changed. Publishing the rules that already hold is refused.
- **Explicit acceptance.** Each player accepts or declines the version that holds; being at the table accepts nothing.
  A player answers the version they read: when another one was published meanwhile, the answer is refused.
- **Re-acceptance.** A version that changes the rules asks every player again; one that only fixes a typo or clarifies
  a sentence is published without asking, and the acceptances of the earlier versions still hold.
- **What changed.** Between any two versions: the rules added, removed, moved and changed, line by line within a text,
  the variants chosen and dropped, and the game system before and after. A player whose acceptance is out of date sees
  what changed since the version they accepted.
- **Where everyone stands.** For a roster of players: who accepted, who declined, who has yet to answer, and who
  accepted an earlier version.

The service reads the time from a PSR-20 clock and from nothing else. The facts of a table's rules — published,
accepted, declined, forgotten — go to an observer, each once, by identifiers only.

It is a plain PHP library: one service, a comparer, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Rule`, `RulebookVersion`, `Rulebook`: the rules, their versions and the decisions, bounded and checked | Implemented and tested |
| `TableRules`: publish, accept, decline, forget, status, roster and comparison | Implemented and tested |
| `Diff\RulebookDiffer`: what changed between two versions, rule by rule and line by line | Implemented and tested |
| `View\PlayerStatus`, `View\RosterView`: where the players stand, as plain data | Implemented and tested |
| `Catalogue\SystemVariantCatalogue`: the rule variants of the `tilttek/rpg-systems` systems | Implemented and tested |
| `Store\InMemoryRulebookStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger messages and a Stimulus display of the changes | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Rules, versions and acceptance](doc/rules.md) | Rules, versions, variants, acceptance and re-acceptance, the comparison and the views. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the clock and the catalogue of variants. |
| [Exceptions](doc/exceptions.md) | `TableRulesException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\TableRules\Rule;
use Tilttek\TableRules\TableRules;

$rules = new TableRules($store, $clock, $observer);   // your RulebookStore, a PSR-20 clock, an observer

$rules->publish('campaign-7', 'gm-1', [
    new Rule('phones', 'Phones', 'Phones stay in the bag during the session.', 'table'),
    new Rule('dice', 'Dice', 'Roll in the open; a die off the table is rolled again.'),
], variants: ['flanking'], system: 'srd-5', note: 'The first rules of the campaign.');

$rules->accept('campaign-7', 'player-3', 1);           // the version the player read

$rules->publish('campaign-7', 'gm-1', [
    new Rule('phones', 'Phones', "Phones stay in the bag during the session.\nOn call? Tell the table."),
    new Rule('dice', 'Dice', 'Roll in the open; a die off the table is rolled again.'),
]);                                                     // asks every player again

$status = $rules->status('campaign-7', 'player-3');
// $status->state === AcceptanceState::Outdated, and $status->changes says what changed since version 1

$roster = $rules->roster('campaign-7', ['player-3', 'player-4']);   // send $roster->toArray() to the game master
```

## Requirements

PHP 8.5 or later, with `psr/clock` and `tilttek/rpg-systems`, whose systems give the rule variants.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
