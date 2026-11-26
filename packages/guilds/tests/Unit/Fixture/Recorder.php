<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Tests\Unit\Fixture;

use Tilttek\Guilds\Contract\GuildObserver;
use Tilttek\Guilds\GoalReached;
use Tilttek\Guilds\GuildChanged;

/**
 * An observer that keeps what it was told, for the tests to read.
 */
final class Recorder implements GuildObserver
{
    /**
     * @var list<GuildChanged>
     */
    public array $changes = [];

    /**
     * @var list<GoalReached>
     */
    public array $reached = [];

    public function changed(GuildChanged $event): void
    {
        $this->changes[] = $event;
    }

    public function goalReached(GoalReached $event): void
    {
        $this->reached[] = $event;
    }

    /**
     * The changes told, each as `change member by`, a dash for a missing reference.
     *
     * @return list<string>
     */
    public function summary(): array
    {
        return array_map(static fn (GuildChanged $event): string => \sprintf('%s %s %s %s', $event->guild, $event->change->value, $event->member ?? '-', $event->by ?? '-'), $this->changes);
    }

    /**
     * The goals reached, each as `goal guild points moment`.
     *
     * @return list<string>
     */
    public function reachedSummary(): array
    {
        return array_map(static fn (GoalReached $event): string => \sprintf('%s %s %d %s', $event->goal, $event->guild, $event->points, $event->at->format('Y-m-d H:i:s')), $this->reached);
    }
}
