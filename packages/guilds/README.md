# tilttek/guilds

**Guilds of members: open or on request, run by a founder and administrators, with a capacity and a group goal their members reach together.**

tilttek/guilds keeps the lasting communities of an application — a guild, a club, a company of players. A member
founds a guild under a reference the application gives, `guild:44`; others enter it at once when it is open, or by a
request its founder or an administrator approves. The founder names the administrators, hands the guild to another
member, or dissolves it; administrators approve and decline requests, remove members and edit the guild. A guild holds
at most its capacity, 2 to 1000 members, and a few metadata entries the application reads — a motto, a banner, a
language. Nobody is ever removed for being inactive: a member leaves, or is removed by someone who may.

A guild is a lasting community, not a party for one quest. Its members are the circle the other packages read: the
team of a team quest of `tilttek/quests`, through `GuildTeamMembership`, and the circle a board of
`tilttek/leaderboards` ranks them among. Beside them, the library keeps the guild's **group track**: a goal its founder
or an administrator opens — *play 200 games in December* — which every member's contributions of points advance
together, until its target is met, its deadline passes, or it is closed. The track ranks nobody inside the guild: it
tells the total, how many brought points, and each member their own share.

It is for a community site, a game site or a club platform. It is a plain PHP library whose dependencies are
`phil/resource`, the opaque reference of a member or a guild, `psr/clock`, and `tilttek/quests`, whose team contract it
implements. Members are references the application gives, `member:44`: the library never reads a name, an e-mail or an
account. Points are counters of the application, never money.

## Status

| Part | State |
|---|---|
| `Guild`, `Admission`, `Metadata`, `Role`, `Membership`, `JoinRequest`: guilds, their admission, capacity, metadata and roles | Implemented and tested |
| `Guilds`: found, join, request, approve, decline, leave, remove, promote, demote, transfer, update, dissolve, read, erase | Implemented and tested |
| `Goal`, `Contribution`, `GoalProgress`, `Goals`: the group track towards a common goal, with keys, deadlines and erasure | Implemented and tested |
| `GuildTeamMembership`: the guilds as the teams of `tilttek/quests` | Implemented and tested |
| `Contract\GuildStore`, `Contract\GoalStore`, with their in-memory implementations | Implemented and tested |
| `Contract\GuildObserver`, `GuildChanged`, `GoalReached`: what is told, identifiers only | Implemented and tested |
| `Limits`: guilds per member, waiting requests per guild, open goals per guild | Implemented and tested |
| A Symfony bundle: Doctrine stores, services, forms and translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Guilds and members](doc/guilds.md) | Admission, roles, capacity, metadata, requests, transfer, dissolution, erasure. |
| [The group track](doc/goals.md) | Goals, contributions, keys, deadlines, progress and what is told. |
| [Quests and leaderboards](doc/quests-and-leaderboards.md) | A guild as the team of a quest, and as the circle of a board. |
| [Extension points](doc/extension-points.md) | Stores, observers, limits and the clock. |
| [Exceptions](doc/exceptions.md) | `GuildsException` and its exceptions, with the refusals and their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that uses guilds. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/guilds
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Guilds\Admission;
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Store\InMemoryGoalStore;
use Tilttek\Guilds\Store\InMemoryGuildStore;

$guilds = new Guilds(new InMemoryGuildStore(), $clock);
$goals = new Goals(new InMemoryGoalStore(), $guilds, $clock);

$guild = ResourceReference::of('guild', '44');
$founder = ResourceReference::of('member', '1');
$member = ResourceReference::of('member', '2');

$guilds->found($guild, 'The Lantern Bearers', $founder, Admission::OnRequest);
$guilds->join($guild, $member);                  // a JoinRequest
$guilds->approve($guild, $member, $founder);     // a Membership

$goals->open('lanterns-december', $guild, 'Play 200 games in December', 200, $founder);
$goals->contribute('lanterns-december', $member, 3, 'game:9812');
$goals->progress('lanterns-december')->percent(); // 1
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
