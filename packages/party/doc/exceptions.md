# Exceptions

Every exception of the library implements `Exception\PartyException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `PartyRefused` | The party's rules refuse the act; `refusal` says why | The refusal's message, below |
| `UnknownParty` | No party holds the reference, no loot the key, or no note the key | `No party is known as "…".`, `The party "…" holds no loot under the key "…".`, `The party "…" holds no note under the key "…".` |
| `InvalidParty` | A name, a key, a currency, a note's text, an amount, a quantity, a role, a role catalogue or a limit out of bounds | What is out of bounds, and the bound |

`PartyRefused` extends `DomainException`, `UnknownParty` `OutOfBoundsException`, `InvalidParty`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyFormed` | A party already holds this reference. |
| `AlreadyMember` | This member already belongs to the party. |
| `NotMember` | This member does not belong to the party. |
| `Full` | The party holds as many members as allowed. |
| `TooManyParties` | This member belongs to as many parties as allowed. |
| `LootExists` | The party already holds loot under this key. |
| `TooMuchLoot` | The party holds as many loot entries as allowed. |
| `NotEnoughLoot` | The party holds fewer of this loot than asked. |
| `NoteExists` | The party already holds a note under this key. |
| `TooManyNotes` | The party holds as many notes as allowed. |
| `NotEnoughFunds` | The purse holds less of this currency than asked. |
| `NoOneToShare` | No member is there to receive a share. |

A new value is checked before the limits and the keys already taken: forming, joining, adding loot or writing a note
with an invalid name, key or text throws `InvalidParty` even when the reference or the key is already taken.

The exceptions of `phil/resource` and `tilttek/rpg-systems` reach the caller unchanged when a reference is malformed or
a system is read.
