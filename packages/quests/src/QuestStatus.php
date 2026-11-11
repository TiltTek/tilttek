<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Phil\Resource\ResourceReference;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Schedule\Occurrence;

/**
 * A quest as a member sees it: where it stands, its occurrence open now if any, the progress of its subject — the
 * member, their team or the community —, the completion that accomplished it, and the member's team.
 */
final readonly class QuestStatus
{
    public function __construct(
        public Quest $quest,
        public QuestState $state,
        public QuestProgress $progress,
        public ?Occurrence $occurrence = null,
        public ?Completion $completion = null,
        public ?ResourceReference $team = null,
    ) {
    }
}
