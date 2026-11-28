# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Rosters and factions

| Type | Role |
|---|---|
| `Factions` | The service: create, add, change and remove a faction, change the scale, shift or set a standing, set a rank, set and end relations, hand over, delete, read the history, and view. |
| `Roster` | The factions of one campaign, its scale and the relations between its factions, checked as a whole. |
| `Faction` | A faction found by its key: its name, summary, ranks, standings, members and secrecy. |
| `Rank` | A rank of a faction, by its key, with its title. |
| `Membership` | The rank a character holds within a faction. |
| `Standing` | Where a subject stands with a faction. |
| `Subject` | The party, or one character, and its key. |
| `Scale` | The bounds, the start and the tiers of the standings of a campaign. |
| `Tier` | A named step of a scale, from its lowest standing. |
| `Relation` | How two factions stand with each other, the pair kept lower key first. |
| `Stance` | Allied, friendly, neutral, rival, hostile or war. |
| `Reason` | Why a standing, a rank or a relation changed, and the session. |

## History

| Type | Role |
|---|---|
| `History\Entry` | One change of the history: its campaign, faction, reason, author and time. |
| `History\StandingEntry` | A standing moved, before and after. |
| `History\RankEntry` | A rank changed, before and after. |
| `History\RelationEntry` | A relation changed, before and after. |

## Views

| Type | Role |
|---|---|
| `View\RosterView` | A roster as its reader sees it: the scale, the factions and the relations they know of. |
| `View\FactionView` | A faction as shown, with the standings and members its reader may see. |
| `View\StandingView` | A standing as shown: the subject, the value and its tier. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\RosterStore` | Keeps the rosters at a version with their history, deletes them and reads the history of a faction. |
| `Contract\RosterObserver` | Told of the events of a roster. |
| `Store\InMemoryRosterStore` | The rosters and their history in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\RosterEvent` | A fact of a roster, by identifiers only. |
| `Event\RosterCreated` | A roster was created. |
| `Event\FactionAdded` | A faction was added. |
| `Event\FactionChanged` | A faction's details changed. |
| `Event\FactionRemoved` | A faction was removed. |
| `Event\ScaleChanged` | The scale changed. |
| `Event\StandingChanged` | A standing moved. |
| `Event\RankChanged` | A rank changed. |
| `Event\RelationChanged` | A relation changed. |
| `Event\RosterHandedOver` | The roster changed hands. |
| `Event\RosterDeleted` | The roster was deleted. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\FactionsException` | Every exception of the library. |
| `Exception\InvalidRoster` | A value out of its shape or bounds, or a name the roster lacks. |
| `Exception\RosterNotFound` | No roster for the campaign. |
| `Exception\RosterRefused` | A call not allowed. |
| `Exception\RosterConflict` | The roster changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of identifiers and keys. Internal. |
| `Internal\Text` | The shape of texts. Internal. |
