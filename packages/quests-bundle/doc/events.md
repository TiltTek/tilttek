# Events

| Event | Dispatched when |
|---|---|
| `Tilttek\Quests\QuestCompleted` | A quest is accomplished: the quest, the completion, `rewards()` and `beneficiaries` |
| `Tilttek\QuestsBundle\Infrastructure\Event\DuelWon` | A side accomplished a duel first, right after its `QuestCompleted` |
| `Tilttek\QuestsBundle\Infrastructure\Event\DuelExpired` | A duel's time ran out with no winner, or its offer went unanswered |

## Giving the rewards

The rewards are declared, never executed: the application gives them through the packages it has.

```php
#[AsEventListener]
public function __invoke(QuestCompleted $event): void
{
    foreach ($event->beneficiaries as $member) {
        foreach ($event->rewards() as $reward) {
            match ($reward->kind) {
                'points' => $this->points->credit($member, $reward->amount, $reward->code, 'quest:'.$event->completion->quest.':'.$event->completion->occurrence),
                'badge' => $this->badges->award($member, $reward->code),
                default => null,
            };
        }
    }
}
```

Derive the key of a movement of points from the completion — quest, occurrence, member —, so that giving it twice
counts once. A rebuild does not dispatch `QuestCompleted` again for a quest accomplished again.
