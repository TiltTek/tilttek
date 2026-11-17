# Following a quest

## Admission

- **`Automatic`** — every member follows the quest as soon as it is open, without asking; all their activity in the
  occurrence counts.
- **`OnAcceptance`** — only the activity after the member accepted the quest counts, and only the activity of members
  who accepted it is kept.

A team quest and a duel are always joined by acceptance: the first under a team the member belongs to, the second
when a member accepts a challenge.

```php
$quests = new Quests($evaluator, $clock, $teamMembership);
$quests->accept($member, 'winter-reading');
$quests->accept($member, 'raid', ResourceReference::of('team', 'red'));
$quests->abandon($member, 'winter-reading');
```

Accepting is refused when the quest is a duel, closed, locked behind the quest it follows, accomplished already in its
occurrence, a team quest without a team or another quest with one. Accepting a quest followed already returns the
enrolment as it is; a team member who wants another team abandons the quest first.

### Team membership

Before a member joins a team quest under a team — and each time they accept it again — `Quests` asks the
`TeamMembership` it was given whether the member belongs to that team, and refuses the quest with `QuestRefused` when
they do not. The application implements it over its own records of the teams, on the server: the team a request names
is never taken as proof. Without one, `Quests` uses `NoTeamMembership`, under which no member belongs to any team, so
a team quest is never joined by default.

```php
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contract\TeamMembership;

final readonly class GuildMembership implements TeamMembership
{
    public function __construct(private GuildRepository $guilds)
    {
    }

    public function isMember(ResourceReference $member, ResourceReference $team): bool
    {
        return 'guild' === $team->key && $this->guilds->hasMember($team->id, $member->toString());
    }
}
```

A member who leaves the team keeps the enrolment they had; the application abandons it for them when they leave.

## Abandonment

Abandoning stops the member's activity from counting from that moment. Abandoning a quest followed automatically
writes an enrolment for it; accepting it again takes it up with everything it counted. Abandoning a quest accepted, then
accepting it again, counts from the new acceptance. An accomplished quest can no longer be abandoned.

## Where a quest stands

`Quests::status($member, $code)` reads a `QuestStatus`: the quest, its `QuestState`, its occurrence open now, the
progress of its subject — the member, the member's team or the community —, the completion that accomplished it, and
the team.

| State | When |
|---|---|
| `Closed` | Not open now, and not accomplished in its last occurrence |
| `Locked` | Open, but the quest it follows is not accomplished yet |
| `Available` | Open, waiting for the member to accept it — or to join it under a team; a duel open now |
| `UnderWay` | Followed, its steps advancing |
| `Abandoned` | The member gave it up |
| `Completed` | Accomplished in its current occurrence — or in its last one, once closed |

`Quests::board($member)` reads the status of every quest but the duels; `Quests::completions($subject)` reads the
journal of the quests a member, a team or the community accomplished.
