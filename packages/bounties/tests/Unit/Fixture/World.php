<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Bounties\Contract\BountyStore;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Store\InMemoryBountyStore;

/**
 * Ana offers bounties on questions, which Bea, Cy and Dee answer; everything in memory, at a time in Montréal. Ana
 * holds 1 000 points.
 */
final class World
{
    public const string NOW = '2026-11-13 20:00:00 America/Toronto';

    public MockClock $clock;

    public InMemoryBountyEscrow $points;

    public Recorder $events;

    public Bounties $bounties;

    public function __construct(
        public BountyStore $store = new InMemoryBountyStore(),
        ?BountyEscrow $escrow = null,
        BountyRules $rules = new BountyRules(),
    ) {
        $this->clock = new MockClock(self::NOW);
        $this->points = new InMemoryBountyEscrow();
        $this->points->fund(self::ana(), 1000);

        $this->events = new Recorder();
        $this->bounties = new Bounties($this->store, $escrow ?? $this->points, $this->events, $rules, $this->clock);
    }

    public function draft(string $id = 'b1', int $amount = 100, string $subject = '42', string $ends = '+7 days'): BountyDraft
    {
        return new BountyDraft($id, self::question($subject), BountyReason::Detail, new Prize($amount), $this->in($ends));
    }

    public function in(string $modifier): DatePoint
    {
        return $this->clock->now()->modify($modifier);
    }

    public static function question(string $id = '42'): ResourceReference
    {
        return ResourceReference::of('question', $id);
    }

    public static function ana(): ResourceReference
    {
        return ResourceReference::of('member', 'ana');
    }

    public static function bea(): ResourceReference
    {
        return ResourceReference::of('member', 'bea');
    }

    public static function cy(): ResourceReference
    {
        return ResourceReference::of('member', 'cy');
    }

    public static function dee(): ResourceReference
    {
        return ResourceReference::of('member', 'dee');
    }
}
