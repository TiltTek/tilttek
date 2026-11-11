<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Event;

use Tilttek\Quests\Duel\Duel;

/**
 * A side accomplished a duel's quest first: `#[AsEventListener] public function __invoke(DuelWon $event)`. The winner's
 * `QuestCompleted` is dispatched just before, with the quest's rewards.
 */
final readonly class DuelWon
{
    public function __construct(
        public Duel $duel,
    ) {
    }
}
