<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

/**
 * Where an entry stands: its days still being spent, its days spent and its roll awaited, its outcome known and
 * awaiting the game master, confirmed, or cancelled.
 */
enum Stage: string
{
    case Queued = 'queued';
    case AwaitingRoll = 'awaiting_roll';
    case Resolved = 'resolved';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Whether the entry still takes its place in the queue of its character.
     */
    public function isOpen(): bool
    {
        return self::Confirmed !== $this && self::Cancelled !== $this;
    }
}
