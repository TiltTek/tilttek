# Encounters and participants

## An encounter

`EncounterPlanner::prepare()` opens an encounter: its identifier, its game master, the key of its game system, an
optional title of one line up to 200 characters, and participants, or none yet. When `Rulebooks` are given, a system
they declare no rules for is refused with `UnknownSystem`. An identifier already kept is refused with
`EncounterConflict`.

Identifiers — of an encounter, a member, a participant, a record, a system, a band — are 1 to 128 letters, digits,
`_`, `.`, `:` or `-`, starting with a letter, never a name or an address. The application keeps names, descriptions
and statistics in its own records.

Only the game master changes an encounter: every change names who calls, and a call by anyone else is refused with
`EncounterRefused`. The application says who is calling; the service trusts it.

## Participants

A `Participant` is one line of the encounter:

| Value | What it is |
|---|---|
| `id` | Its identifier, unique in the encounter, up to 124 characters. |
| `kind` | `Kind::Character`, `Kind::Adversary` or `Kind::Ally`. |
| `rating` | Its level or challenge as the game system writes it: `3`, `1/4`, `-1`, `12+`; up to 16 letters, digits, `/`, `.`, `+` or `-`. |
| `controller` | The member who plays it: a player for their character, the game master for the others. |
| `quantity` | 1 to 50 copies of one model; a character is always one. |
| `hidden` | Whether the players do not see it. |
| `initiativeBonus` | What it adds to its initiative, -99 to 99, or null when the system adds nothing. |
| `ref` | The identifier of the application's record — a character sheet, a stat block — or null. |

The copies of a participant are combatants of their own: `wolves` of three copies gives `wolves.1`, `wolves.2` and
`wolves.3`; a participant of one copy is its own combatant. An encounter holds up to 100 participants and 100
combatants, every copy counted; a combatant identifier that comes twice — `wolves.2` named as another participant —
is refused.

## Changing an encounter

| Call | What it does |
|---|---|
| `add()` | Adds a participant last, or right `before` or `after` another one. |
| `change()` | Replaces a participant by the one of the same identifier, in its place. |
| `move()` | Moves a participant right `before` or `after` another one; exactly one of the two is named. |
| `remove()` | Removes a participant. |
| `retitle()` | Gives the encounter another title, or none with null. |

The order is the one the game master sets, by placing a participant beside another: there are no numbers to keep in
sequence. A change that changes nothing — the same title, the same participant, a move that keeps the order — keeps
nothing and tells nothing. Naming a participant the encounter does not hold is refused with `EncounterRefused`; naming
both sides at once is refused with `InvalidEncounter`.

Each change reads the encounter, changes it, keeps it at the next revision, then tells the observer. When the
encounter changed since it was read, the store refuses it with `EncounterConflict`, and nothing is kept.

## Assessing

`assess()` gives the difficulty of the encounter by the rules of its game system, as [Difficulty](difficulty.md)
describes. An application that previews a participant before adding it asks the rules directly:
`$rulebooks->for($system)->assess([...$encounter->participants, $candidate])`.

## Launching

`launch()` keeps the encounter launched, then hands its roster to the initiative tracker, as [Initiative](initiative.md)
describes, and tells `EncounterLaunched`. A launched encounter no longer changes: every change, and a second launch, is
refused with `EncounterRefused`. It is still read, assessed and given as a roster. An encounter without participant is
not launched.

## Forgetting

`forget()` removes the encounter from the store, launched or not, and tells `EncounterForgotten`.
