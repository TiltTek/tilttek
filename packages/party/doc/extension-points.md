# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\PartyStore` | Where parties, members, purses, loot and notes are kept | `Store\InMemoryPartyStore` in tests |
| `Contract\PartyObserver` | What to do with a change of a party | None |
| `Limits` | Its bounds on members per party, parties per member, loot entries and notes per party | 12, 10, 500 and 200 |
| `Composition\RoleCatalogue` | The roles a composition is read against, from a system or built by hand, maybe none | `RoleCatalogue::none()` for a game without roles |
| `Psr\Clock\ClockInterface` | The moment of each act | — |

## Writing a party store

A `PartyStore` names parties and members by their references written `kind:identifier`, and loot and notes by their
keys inside their party. It keeps:

- the parties, by reference — `party()`, `saveParty()`, and `removeParty()`, which removes the party's members, purse,
  loot and notes with it;
- the members, one entry per party and member — `member()`, `saveMember()`, which replaces the earlier entry,
  `removeMember()`, `countMembers()`, and `members()` and `membershipsOf()`, the earliest arrived first;
- the purse of each party — `purse()`, an empty `Purse` when nothing was saved, and `savePurse()`;
- the loot, one entry per party and key — `lootItem()`, `saveLoot()`, which replaces the entry of the same key,
  `removeLoot()`, `countLoot()`, and `loot()`, the earliest added first;
- the notes, one per party and key — `note()`, `saveNote()`, `removeNote()`, `countNotes()`, `notes()`, the earliest
  written first, and `notesBy($author)`, the notes a member wrote in every party.

Moments that tie are read in the byte order of the party's reference then the member's reference or the key. The
library checks a party's limits before it writes, and a purse's balance before it withdraws: an application that
serves concurrent requests runs each act of `Parties` in a transaction, or under a lock on the party, so that two
withdrawals never spend the same coins and two characters never take the last place together.

## Observers

A `PartyObserver` is told a `PartyChanged` after each act, once the store wrote it. The event holds identifiers only —
the party, the member concerned, the subject (a currency, a loot key or a note key), the acting reference — with the
`Change` and the moment; amounts, names and texts are never in it. An observer relays the event — a notification, a
message on a bus — and keeps no state between two calls.

| `Change` | Member | Subject |
|---|---|---|
| `Formed`, `Updated`, `Disbanded` | — | — |
| `Joined`, `Left`, `RolesAssigned`, `StandingChanged`, `Forgotten` | The member | — |
| `Deposited`, `Withdrawn`, `Distributed` | — | The currency |
| `LootAdded`, `LootGiven`, `LootRemoved` | The holder, or none for the stash | The loot key |
| `NoteWritten`, `NoteEdited`, `NoteRemoved` | The author, or none once erased | The note key |
