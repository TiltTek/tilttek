<?php

declare(strict_types=1);

namespace Tilttek\Quests\Duel;

use Tilttek\Quests\Progress\QuestProgress;

/**
 * A duel and how far each side is with its quest.
 */
final readonly class DuelStanding
{
    public function __construct(
        public Duel $duel,
        public QuestProgress $challenger,
        public QuestProgress $opponent,
    ) {
    }
}
