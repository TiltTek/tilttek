# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\GuildStore` | Where guilds, memberships and waiting requests are kept | `Store\InMemoryGuildStore` in tests |
| `Contract\GoalStore` | Where goals and contributions are kept | `Store\InMemoryGoalStore` in tests |
| `Contract\GuildObserver` | What to do with a change of a guild or a goal reached | None |
| `Limits` | Its bounds on guilds per member, waiting requests and open goals | 10, 100 and 10 |
| `Psr\Clock\ClockInterface` | The moment of each act | — |

## Writing a guild store

A `GuildStore` names guilds and members by their references written `kind:identifier`. It keeps:

- the guilds, by reference — `guild()`, `saveGuild()`, and `removeGuild()`, which removes the guild's members and
  requests with it;
- the memberships, one per guild and member — `membership()`, `saveMembership()`, which replaces the earlier one,
  `removeMembership()`, `countMembers()`, and `memberships()` and `membershipsOf()`, the earliest entered first;
- the waiting requests, one per guild and member — `request()`, `saveRequest()`, `removeRequest()`, `countRequests()`,
  and `requests()` and `requestsOf()`, the oldest first.

Moments that tie are read in the byte order of the guild's then the member's reference. The library checks a guild's
capacity before writing a membership: an application that serves concurrent requests runs each act in a transaction,
or under a lock on the guild.

## Writing a goal store

A `GoalStore` keeps the goals by key — `goal()`, `saveGoal()`, which replaces the goal of the same key, and
`goalsOf()`, the earliest opened first, ties by key —, and their contributions — `addContribution()`,
`contribution($goal, $key)`, the one under a key, and the sums `points()`, `pointsOf()` and `contributors()`, which
counts erased members as one together. `forget($member)` sets the member of their contributions to null and keeps the
points. A contribution runs in a transaction, or under a lock on the goal, so that the goal is reached once.

## Observers

A `GuildObserver` is told a `GuildChanged` after each act and a `GoalReached` when a goal meets its target, after the
store wrote them. The events hold identifiers only; an observer relays them — a notification, a message on a bus — and
keeps no state between two calls.
