# Quests and leaderboards

A guild provides the circle of its members; the other packages decide what the circle does.

## The team of a quest

`tilttek/quests` asks a `TeamMembership` whether a member belongs to the team they join a team quest under.
`GuildTeamMembership` answers from the guild store, never from a team the browser named:

```php
use Tilttek\Guilds\GuildTeamMembership;
use Tilttek\Quests\Quests;

$quests = new Quests($evaluator, $clock, new GuildTeamMembership($guildStore));

$quests->accept($member, 'winter-campaign', ResourceReference::of('guild', '44'));
```

The team quest stays in `tilttek/quests`: its steps, its schedule and its rewards. The guild only says who is in it.

## The circle of a leaderboard

`tilttek/leaderboards` ranks a board among a `Circle` of members, or adds the scores of a `Team`. The guild's members
give both:

```php
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Team;

$circle = new Circle(...$guilds->references($guild));
$leaderboards->standing('games-played', $viewer, among: $circle);

$leaderboards->teams('games-played', [new Team('lanterns', $circle)]);
```

A guild holds at most 1000 members, as a circle does. `tilttek/leaderboards` is suggested, not required: an application
without boards installs nothing more.
