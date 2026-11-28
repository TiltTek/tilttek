# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/factions` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-27

### Added

- `Roster`: the factions of one campaign, held by its game master — its scale, up to 64 factions each key once and up
  to 512 relations each pair once between factions of the roster —, checked as a whole, each change returning a new
  value.
- `Scale` and `Tier`: whole-number standings between -100 and 100, a start for a subject no change has touched, and 1
  to 12 named tiers cutting the scale from its lowest up; `Scale::standard()` runs from -3 to 3; `tier()`,
  `contains()` and `clamp()`.
- `Faction`, `Rank`, `Membership` and `Standing`: a faction found by its key, its name and summary, up to 16 ranks from
  the lowest to the highest, up to 64 standings each subject once, up to 64 members each holding a rank of the faction,
  and whether it is secret; texts valid UTF-8, trimmed, without control characters in a line nor direction overrides
  anywhere; `Faction::outranks()`.
- `Subject`: the party or one character, keyed `party` or `character:` and the identifier, read back by
  `Subject::fromKey()`.
- `Relation` and `Stance`: how two factions stand with each other, the same both ways, kept lower key first, with a
  note and whether it is secret; a faction has no relation with itself.
- `Factions`: create a roster with where the campaign begins; add a faction without standings or members, change its
  details while keeping them, remove it with its relations; change the scale when every standing fits; shift a standing
  by 1 to 200 steps, stopping at the ends, or set it on the scale; give, change or take a rank; set or end a relation;
  hand over; delete; a change that leaves the roster as it was keeps and tells nothing; it keeps no state between calls.
- `Reason` and the history: each move of a standing, a rank or a relation takes the game master's words and an optional
  session, and keeps a `History\StandingEntry`, `History\RankEntry` or `History\RelationEntry` with the value before and
  after, the member and the time from a `Psr\Clock\ClockInterface`, in the same write as the roster;
  `Factions::history()` reads those of a faction, the newest first, up to 500.
- `View\RosterView`, `View\FactionView` and `View\StandingView`: the game master's view, with every standing, member
  and secret; the player's view, without secret factions or relations, with the party's standing and only their own
  standing and rank; each standing with its tier, and `toArray()` for a page.
- The events `RosterCreated`, `FactionAdded`, `FactionChanged`, `FactionRemoved`, `ScaleChanged`, `StandingChanged`,
  `RankChanged`, `RelationChanged`, `RosterHandedOver` and `RosterDeleted`, by identifiers only, told once to a
  `Contract\RosterObserver`.
- `Contract\RosterStore`, kept at a version with its history in one write, deleted with it, and read by faction, and
  `Store\InMemoryRosterStore` for tests.
- The exceptions `InvalidRoster`, `RosterNotFound`, `RosterRefused` and `RosterConflict`, under `FactionsException`.
- Benchmarks of the largest roster: viewed by the game master and by a player, a standing shifted.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
