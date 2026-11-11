# Events

The projection and the sweep tell the application's event dispatcher:

| Event | Dispatched when | Carries |
|---|---|---|
| `Tilttek\ProgressionBundle\Infrastructure\Event\LevelUp` | A subject reaches a higher level of a track | `change`: a `LevelChange` (track, subject, season, from, to, moment, activity) |
| `Tilttek\ProgressionBundle\Infrastructure\Event\LevelDown` | A cancellation takes a subject back to a lower level | `change` |
| `Tilttek\Progression\ReachedMilestone` | A subject passes a milestone, once per season | track, milestone, subject, member, season, moment, activity |
| `Tilttek\Progression\Streak\StreakBroken` | A member's streak breaks, once | streak, member, length, last active unit, moment |

## Rewarding a milestone

```php
#[AsEventListener]
final readonly class RewardMilestones
{
    public function __construct(private Points $points) {}

    public function __invoke(ReachedMilestone $reached): void
    {
        $this->points->grant($reached->member, 100, 'milestone:'.$reached->track.':'.$reached->milestone.':'.$reached->subject->toString().':'.$reached->season);
    }
}
```

The key makes the grant written once even if the listener runs twice. During a rebuild only the levels above those
held before are told, and neither a level lost nor a broken streak is; milestones are never told again.
