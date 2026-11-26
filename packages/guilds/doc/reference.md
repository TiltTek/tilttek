# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Guilds` | Founds, admits, runs, reads and erases guilds and their members |
| `Guild` | A guild: reference, name, admission, capacity, metadata, founding moment |
| `Admission` | `Open` or `OnRequest` |
| `Metadata` | The application's entries on a guild, bounded |
| `Role` | `Founder`, `Administrator` or `Member`, and what each may do |
| `Membership` | A member of a guild, their role and since when |
| `JoinRequest` | A member's waiting request to enter a guild |
| `Limits` | Guilds per member, waiting requests per guild, open goals per guild |
| `Goals` | Opens, feeds, reads, closes and erases the group track's goals |
| `Goal` | A goal: key, guild, name, target, opening, deadline, reached and closed moments |
| `Contribution` | Points a member brought to a goal, under a key or not |
| `GoalProgress` | Where a goal stands: points, contributors, share, remainder |
| `GuildTeamMembership` | The guilds as the teams of `tilttek/quests` |
| `GuildChanged` | Told after each act on a guild, identifiers only |
| `Change` | What happened to a guild or a member |
| `GoalReached` | Told when a goal meets its target, identifiers only |
| `Refusal` | Why an act is refused |
| `Contract\GuildStore` | Where guilds, memberships and requests are kept |
| `Contract\GoalStore` | Where goals and contributions are kept |
| `Contract\GuildObserver` | What the application does with the events |
| `Store\InMemoryGuildStore` | The guild store in memory |
| `Store\InMemoryGoalStore` | The goal store in memory |
| `Exception\GuildsException` | Every exception of the library |
| `Exception\GuildRefused` | An act refused, with its `Refusal` |
| `Exception\UnknownGuild` | No guild under a reference, or no goal under a key |
| `Exception\InvalidGuild` | A value out of bounds |
