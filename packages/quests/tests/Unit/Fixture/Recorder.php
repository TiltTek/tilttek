<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestObserver;

/**
 * An observer that writes down what it is told, in a few words per event.
 */
final class Recorder implements QuestObserver
{
    /**
     * @var list<string>
     */
    private array $events = [];

    /**
     * @var list<QuestCompleted>
     */
    public array $completed = [];

    #[Override]
    public function completed(QuestCompleted $completed): void
    {
        $this->completed[] = $completed;
        $this->events[] = \sprintf(
            'completed %s by %s for %s',
            $completed->quest->code,
            $completed->completion->subject->toString(),
            implode(',', array_map(static fn (ResourceReference $member): string => $member->toString(), $completed->beneficiaries)),
        );
    }

    #[Override]
    public function duelWon(Duel $duel): void
    {
        $this->events[] = \sprintf('duel %s won by %s', $duel->key, $duel->winner?->toString() ?? '?');
    }

    #[Override]
    public function duelExpired(Duel $duel): void
    {
        $this->events[] = \sprintf('duel %s expired', $duel->key);
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
