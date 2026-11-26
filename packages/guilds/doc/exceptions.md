# Exceptions

Every exception of the library implements `Exception\GuildsException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `GuildRefused` | The guild's rules refuse the act; `refusal` says why | The refusal's message, below |
| `UnknownGuild` | No guild holds the reference, or no goal the key | `No guild is known as "…".`, `No goal is known as "…".` |
| `InvalidGuild` | A guild's name, capacity or metadata, a goal's key, name, target or deadline, a contribution's points or key, or a limit out of bounds | What is out of bounds, and the bound |

`GuildRefused` extends `DomainException`, `UnknownGuild` `OutOfBoundsException`, `InvalidGuild`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `AlreadyFounded` | A guild already holds this reference. |
| `AlreadyMember` | This member already belongs to the guild. |
| `AlreadyRequested` | This member already asked to join the guild. |
| `NotMember` | This member does not belong to the guild. |
| `NoRequest` | This member has no request to join the guild. |
| `Full` | The guild holds as many members as its capacity. |
| `TooManyGuilds` | This member belongs to, or asked to join, as many guilds as allowed. |
| `TooManyRequests` | The guild holds as many waiting requests as allowed. |
| `NotAllowed` | The role of this member does not allow this act. |
| `FounderStays` | The founder hands the guild to another member before leaving it. |
| `BelowMembers` | The capacity cannot go below the members the guild holds. |
| `GoalExists` | A goal already holds this key. |
| `TooManyGoals` | The guild holds as many open goals as allowed. |
| `GoalOver` | The goal is reached, closed or past its deadline. |

The exceptions of `phil/resource` reach the caller unchanged when a reference is malformed.
