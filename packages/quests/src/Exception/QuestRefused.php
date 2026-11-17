<?php

declare(strict_types=1);

namespace Tilttek\Quests\Exception;

use DomainException;

/**
 * What a member asked is refused: a quest that is closed, locked behind another or already accomplished, a team quest
 * joined under a team the member does not belong to, a quest that cannot be abandoned, a duel answered by someone else
 * than its party or no longer waiting for that answer.
 */
final class QuestRefused extends DomainException implements QuestsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
