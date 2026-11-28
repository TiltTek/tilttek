# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Parties` | Forms, runs, reads and erases parties, their members, purse, loot and notes |
| `Party` | A party: reference, campaign, name, game system, forming moment |
| `Member` | A member of a party: its reference, roles, standing and arrival |
| `Standing` | `Active` or `Away` |
| `Purse` | The party's coins by currency, bounded, as a value |
| `Split` | Each member's share of a currency, and the remainder |
| `LootItem` | Loot under a key: name, quantity, holder or stash, adding moment |
| `Note` | A note under a key: text, author, written and edited moments |
| `Limits` | Members per party, parties per member, loot entries and notes per party |
| `PartyChanged` | Told after each act, identifiers only |
| `Change` | What happened to a party |
| `Refusal` | Why an act is refused |
| `Composition\Role` | A role a system declares: key, name, minimum |
| `Composition\RoleCatalogue` | The roles of a system, from its party roles or built by hand; empty for a game without roles |
| `Composition\CompositionAdvisor` | Reads members against a catalogue |
| `Composition\Composition` | The coverages, the unassigned members, the unknown roles, balance |
| `Composition\RoleCoverage` | A role, the active members who fill it, its coverage |
| `Composition\Coverage` | `Missing`, `Covered` or `Crowded` |
| `Contract\PartyStore` | Where parties and what they hold are kept |
| `Contract\PartyObserver` | What the application does with the events |
| `Store\InMemoryPartyStore` | The store in memory |
| `Exception\PartyException` | Every exception of the library |
| `Exception\PartyRefused` | An act refused, with its `Refusal` |
| `Exception\UnknownParty` | No party under a reference, no loot or note under a key |
| `Exception\InvalidParty` | A value out of bounds |

`Text` is internal: it checks the keys, names and texts, and is not part of the promised API.
