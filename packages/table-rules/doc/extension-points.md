# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the rulebooks and their versions | Implement `Contract\RulebookStore`, and pass it to `TableRules`. |
| Act on what happened | Implement `Contract\TableRulesObserver`, and pass it to `TableRules`. |
| Check the variants against a game system | Pass `Catalogue\SystemVariantCatalogue` over its `tilttek/rpg-systems` systems to `TableRules`, or implement `Contract\VariantCatalogue` for variants of its own. |
| Give the time | Pass a PSR-20 `ClockInterface`. |
| Name the players of a table | Pass them to `TableRules::roster()`; the rulebook knows only those who answered. |
| Say who may publish the rules, and that a player answers for no one else | Check it in the application before calling `TableRules`. |

## The store

`Contract\RulebookStore` keeps each table's `Rulebook` at a revision, and every `RulebookVersion` published:

- `rulebook()` gives a table's rulebook, or null when none was ever kept;
- `version()` gives a version of a table by its number, or null;
- `save()` keeps the rulebook at the next revision, with the version just published when one is given — both or
  neither — and returns the rulebook kept. A rulebook at revision 0 is new, and the store has none for the table; any
  other is kept only when the store holds it at that revision, and `RulesConflict` is raised otherwise, nothing kept.

Two players accepting at once both read revision 7: the first save keeps revision 8, and the second is refused with
`RulesConflict`; the application reads anew and answers again.

The rulebook holds the current version itself, so that the common calls read one record; the earlier versions are read
one at a time, to compare them. `Rulebook` holds strings and integers — the table, the baseline, the revision — the
current version and up to 500 `Acceptance`s, each a player, a version number, a `Decision` whose values are its string
cases, and a moment. A `RulebookVersion` holds integers, strings, a moment, a boolean and up to 200 `Rule`s of four
strings; `toArray()` gives its plain data.

`Store\InMemoryRulebookStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database, the versions apart from the rulebook.

## The observer and its events

`Contract\TableRulesObserver::notify()` is told each fact once the rulebook is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\RulebookPublished` | A new version was published. | its number, and whether it requires acceptance |
| `Event\RulesAccepted` | A player accepted the version that holds. | the player and the version |
| `Event\RulesDeclined` | A player declined the version that holds. | the player and the version |
| `Event\PlayerForgotten` | The decision of a player was forgotten. | the player |

Every event implements `Event\RulesEvent`: the table, an identifier. An answer that changes nothing tells no event. An
application sends a message from them — through Messenger, with the identifiers alone — and reads the rest from the
store: a notice to each player that the rules changed, a mark on the table's page once everyone accepted.

## The catalogue of variants

`Contract\VariantCatalogue::has()` says whether a game system offers a variant; the system is null when the version
names none. `Catalogue\SystemVariantCatalogue` backs it with the rule variants of the `tilttek/rpg-systems` systems; an
application whose variants live elsewhere implements it on its own data. Without one, any identifier is a variant.

## The clock

`TableRules` reads the time from the PSR-20 `ClockInterface` it is given, and from nothing else: the moment a version
is published and the moment a player answers.
