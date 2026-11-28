# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/party` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Party`: a party under the application's reference, for a campaign, with a name of 1 to 100 characters and the key of
  its game system or none.
- `Member` and `Standing`: the members of a party under their own references, each filling 0 to 5 distinct roles by
  key — none by default, for a game without classes or jobs —, active or away — an away member keeps its place and
  counts nowhere.
- `Parties`: forming, renaming, changing the system and disbanding a party; joining, leaving — the loot carried goes back
  to the stash —, assigning roles and setting the standing; reading a party, its members and a member's parties; erasing
  a member from every party, its notes kept without their author. The application decides who may act and passes the
  acting reference, which the events carry.
- `Purse`: the shared coins by currency, whole counts of 1 to 1,000,000,000,000 under at most 20 currency keys, never
  negative; deposits and withdrawals.
- `Split`: shares of a currency among the active members or the members chosen, in whole coins, the remainder kept in
  the purse; `shares()` reads them, `distribute()` takes them out.
- `LootItem`: shared loot under keys, a quantity of 1 to 1,000,000, carried by a member or in the stash; adding, giving,
  stashing and removing.
- `Note`: notes the members share under keys, 1 to 5000 characters, with their author and their written and edited
  moments.
- `Composition\Role`, `Composition\RoleCatalogue`, `Composition\CompositionAdvisor`, `Composition\Composition`,
  `Composition\RoleCoverage` and `Composition\Coverage`: the composition of a party against the party roles a system of
  `tilttek/rpg-systems` declares, or roles given by hand — missing, covered and crowded roles against a
  fair share, unassigned members and unknown roles, as advice and never a refusal. Roles are optional: a catalogue holds
  0 to 50 roles, `RoleCatalogue::none()` and a system without party roles give an empty one, and a composition read
  against it gives no advice (`hasAdvice()` false).
- `Limits`: members per party, parties per member, loot entries and notes per party, by default 12, 10, 500 and 200.
- `Contract\PartyStore`, with `Store\InMemoryPartyStore`.
- `Contract\PartyObserver`, told `PartyChanged` with its `Change`, identifiers only.
- Exceptions under one `PartyException` interface: `PartyRefused` with its `Refusal`, `UnknownParty` and
  `InvalidParty`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
