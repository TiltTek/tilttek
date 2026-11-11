<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Event;

use Tilttek\Quests\Duel\Duel;

/**
 * A duel's time ran out with no winner, or its offer went unanswered: `#[AsEventListener] public function
 * __invoke(DuelExpired $event)`.
 */
final readonly class DuelExpired
{
    public function __construct(
        public Duel $duel,
    ) {
    }
}
