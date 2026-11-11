<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\Total;

/**
 * The contract every experience store passes: extend it with the store to test.
 */
abstract class ExperienceStoreTestCase extends TestCase
{
    abstract protected function store(): ExperienceStore;

    public function testAGainIsKeptOnce(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-11-01 10:00')));
        self::assertFalse($store->add(self::gain('player', 'games', 'member:1', 'k1', 99, '2026-11-01 10:00')));
        self::assertTrue($store->add(self::gain('player', 'wins', 'member:1', 'k1', 5, '2026-11-01 10:00')));
        self::assertTrue($store->add(self::gain('gm', 'games', 'member:1', 'k1', 7, '2026-11-01 10:00')));
        self::assertSame(15, $store->lifetime('player', self::ref('member:1')));
        self::assertSame(7, $store->lifetime('gm', self::ref('member:1')));
        self::assertSame(0, $store->lifetime('player', self::ref('member:2')));
    }

    public function testTotalsBySeason(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00', '2026-10'));
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00', '2026-11'));
        $store->add(self::gain('player', 'wins', 'member:1', 'k2', 5, '2026-11-01 10:00', '2026-11'));
        $store->add(self::gain('player', 'games', 'member:1', 'k3', 1, '2026-11-02 10:00'));
        $store->add(self::gain('player', 'games', 'member:2', 'k4', 100, '2026-11-02 10:00', '2026-11'));
        $store->add(self::gain('gm', 'games', 'member:1', 'k5', 100, '2026-11-02 10:00', '2026-11'));

        self::assertSame(25, $store->total('player', self::ref('member:1'), '2026-11'));
        self::assertSame(10, $store->total('player', self::ref('member:1'), '2026-10'));
        self::assertSame(1, $store->total('player', self::ref('member:1'), ''));
        self::assertSame(36, $store->lifetime('player', self::ref('member:1')));
        self::assertEquals([
            new Total('player', self::ref('member:1'), '2026-10', 10),
            new Total('player', self::ref('member:1'), '2026-11', 25),
        ], $this->sorted($store->seasons('player', self::ref('member:1'))));
        self::assertSame([], $store->seasons('author', self::ref('member:1')));
        self::assertSame([0, 1], array_keys($store->seasons('player', self::ref('member:1'))));
    }

    public function testWhatARuleGaveInAPeriod(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-11-01 00:00'));
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 23:59'));
        $store->add(self::gain('player', 'games', 'member:1', 'k3', 40, '2026-11-02 00:00'));
        $store->add(self::gain('player', 'wins', 'member:1', 'k3', 80, '2026-11-01 12:00'));
        $store->add(self::gain('player', 'games', 'member:2', 'k4', 160, '2026-11-01 12:00'));
        $store->add(self::gain('gm', 'games', 'member:1', 'k5', 320, '2026-11-01 12:00'));

        self::assertSame(30, $store->earned('player', 'games', self::ref('member:1'), self::at('2026-11-01 00:00'), self::at('2026-11-02 00:00')));
        self::assertSame(70, $store->earned('player', 'games', self::ref('member:1'), null, null));
        self::assertSame(60, $store->earned('player', 'games', self::ref('member:1'), self::at('2026-11-01 00:01'), null));
        self::assertSame(10, $store->earned('player', 'games', self::ref('member:1'), null, self::at('2026-11-01 23:59')));
    }

    public function testACancellationRemovesTheGainsOfItsActivity(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-11-01 10:00'));
        $store->add(self::gain('player', 'wins', 'member:1', 'k1', 5, '2026-11-01 10:00'));
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 11:00'));

        $removed = $store->remove('k1');
        self::assertSame([0, 1], array_keys($removed));
        usort($removed, static fn (Gain $a, Gain $b): int => $a->rule <=> $b->rule);

        self::assertEquals([self::gain('player', 'games', 'member:1', 'k1', 10, '2026-11-01 10:00'), self::gain('player', 'wins', 'member:1', 'k1', 5, '2026-11-01 10:00')], $removed);
        self::assertSame([], $store->remove('k1'));
        self::assertSame(20, $store->lifetime('player', self::ref('member:1')));
    }

    public function testTotalsBeforeARebuild(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00', '2026-10'));
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00', '2026-11'));
        $store->add(self::gain('player', 'games', 'member:1', 'k3', 30, '2026-11-02 10:00', '2026-11'));
        $store->add(self::gain('player', 'games', 'member:2', 'k4', 40, '2026-10-02 10:00', '2026-10'));

        self::assertEquals([new Total('player', self::ref('member:1'), '2026-11', 50)], $store->totalsSince(self::at('2026-11-01 10:00')));
        self::assertEquals([
            new Total('player', self::ref('member:1'), '2026-10', 10),
            new Total('player', self::ref('member:1'), '2026-11', 50),
            new Total('player', self::ref('member:2'), '2026-10', 40),
        ], $this->sorted($store->totalsSince(null)));
        self::assertSame([], $store->totalsSince(self::at('2026-12-01')));
        self::assertEquals([new Total('player', self::ref('member:1'), '2026-11', 50), new Total('player', self::ref('member:2'), '2026-10', 40)], $this->sorted($store->totalsSince(self::at('2026-10-02 10:00'))));

        $store->add(self::gain('gm', 'games', 'member:1', 'k5', 5, '2026-11-03 10:00', '2026-11'));
        self::assertEquals([new Total('gm', self::ref('member:1'), '2026-11', 5), new Total('player', self::ref('member:1'), '2026-11', 50)], $this->byTrack($store->totalsSince(self::at('2026-11-02 10:00'))));
    }

    public function testARebuildForgetsAPeriod(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00'));
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00'));
        $store->add(self::gain('player', 'games', 'member:1', 'k3', 30, '2026-11-02 10:00'));

        self::assertSame(2, $store->forget(self::at('2026-11-01 10:00')));
        self::assertSame(10, $store->lifetime('player', self::ref('member:1')));
        self::assertTrue($store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00')));
        self::assertSame(2, $store->forget(null));
        self::assertSame(0, $store->lifetime('player', self::ref('member:1')));
    }

    public function testTheGainsOfASubject(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00', '2026-11'));
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00', '2026-10'));
        $store->add(self::gain('player', 'games', 'member:2', 'k3', 10, '2026-10-01 10:00'));

        self::assertEquals([
            self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00', '2026-10'),
            self::gain('player', 'games', 'member:1', 'k2', 20, '2026-11-01 10:00', '2026-11'),
        ], $store->of(self::ref('member:1')));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::gain('player', 'games', 'member:1', 'k1', 10, '2026-10-01 10:00'));
        $store->add(new Gain('character', 'sessions', self::ref('character:9'), self::ref('member:1'), 'k2', 300, self::at('2026-10-01 10:00')));
        $store->add(new Gain('character', 'sessions', self::ref('member:1'), self::ref('member:3'), 'k3', 1, self::at('2026-10-01 10:00')));
        $store->add(self::gain('player', 'games', 'member:2', 'k4', 10, '2026-10-01 10:00'));

        self::assertSame(3, $store->eraseMember(self::ref('member:1')));
        self::assertSame(0, $store->lifetime('character', self::ref('character:9')));
        self::assertSame(10, $store->lifetime('player', self::ref('member:2')));
        self::assertSame(0, $store->eraseMember(self::ref('member:1')));
    }

    protected static function gain(string $track, string $rule, string $member, string $activity, int $amount, string $at, string $season = ''): Gain
    {
        return new Gain($track, $rule, self::ref($member), self::ref($member), $activity, $amount, self::at($at), $season);
    }

    protected static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    protected static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }

    /**
     * @param list<Total> $totals
     *
     * @return list<Total>
     */
    private function byTrack(array $totals): array
    {
        usort($totals, static fn (Total $a, Total $b): int => $a->track <=> $b->track);

        return $totals;
    }

    /**
     * @param list<Total> $totals
     *
     * @return list<Total>
     */
    private function sorted(array $totals): array
    {
        usort($totals, static fn (Total $a, Total $b): int => [$a->subject->toString(), $a->season] <=> [$b->subject->toString(), $b->season]);

        return $totals;
    }
}
