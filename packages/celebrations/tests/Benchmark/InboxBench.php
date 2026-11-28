<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Limits;
use Tilttek\Celebrations\Store\InMemoryCelebrationStore;

/**
 * What every page a member opens asks of the fullest inbox the default limits allow — 200 pending entries from four
 * sources, among 500 —: the count for the bell, the groups, the next celebration, and an achievement received at the
 * bound, which lapses the earliest pending entry and drops the first settled one.
 */
final class InboxBench
{
    private readonly Celebrations $celebrations;

    private readonly ResourceReference $member;

    private readonly MockClock $clock;

    private int $next = 0;

    public function __construct()
    {
        $this->clock = new MockClock('2026-11-27 10:00:00', 'UTC');
        $this->celebrations = new Celebrations(new InMemoryCelebrationStore(), $this->clock, new Limits());
        $this->member = ResourceReference::of('member', '1');
        $sources = ['badges', 'quests', 'progression', 'collections'];

        for ($i = 1; $i <= Limits::DEFAULT_ENTRIES_PER_MEMBER; ++$i) {
            $source = $sources[$i % 4];
            $this->celebrations->receive(new Achievement($this->member, $source, 'earned', $source.'.item-'.$i, 'seed#'.$i, $this->clock->now()->modify('-'.$i.' minutes'), ['count' => $i]));

            if ($i <= Limits::DEFAULT_ENTRIES_PER_MEMBER - Limits::DEFAULT_PENDING_PER_MEMBER) {
                $this->celebrations->markSeen($this->member, [$i]);
            }
        }
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchCount(): int
    {
        return $this->celebrations->count($this->member);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchNext(): int
    {
        return $this->celebrations->next($this->member)->remaining ?? 0;
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReceiveAtTheBound(): int
    {
        ++$this->next;

        return $this->celebrations->receive(new Achievement($this->member, 'badges', 'earned', 'badges.new', 'bench#'.$this->next, $this->clock->now()))->sequence;
    }
}
