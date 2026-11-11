<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Store\ContributionStore;

/**
 * The contract every contribution store passes: extend it with the store to test.
 */
abstract class ContributionStoreTestCase extends TestCase
{
    abstract protected function store(): ContributionStore;

    public function testAContributionIsKeptOncePerQuestStepAndActivity(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00')));
        self::assertFalse($store->add(self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00', 9)));
        self::assertTrue($store->add(self::contribution('q', 'b', 'member:1', 'k1', '2026-11-02 10:00')));
        self::assertTrue($store->add(self::contribution('r', 'a', 'member:1', 'k1', '2026-11-02 10:00')));
        self::assertTrue($store->add(self::contribution('q', 'a', 'member:2', 'k0', '2026-11-01 10:00', 5)));
        self::assertTrue($store->add(self::contribution('q', 'a', 'member:3', 'k2', '2026-11-03 10:00')));

        self::assertEquals([
            self::contribution('q', 'a', 'member:2', 'k0', '2026-11-01 10:00', 5),
            self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('q', 'b', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('q', 'a', 'member:3', 'k2', '2026-11-03 10:00'),
        ], $store->of('q'));
        self::assertEquals([
            self::contribution('q', 'a', 'member:2', 'k0', '2026-11-01 10:00', 5),
            self::contribution('q', 'a', 'member:3', 'k2', '2026-11-03 10:00'),
        ], $store->of('q', [ResourceReference::fromString('member:2'), ResourceReference::fromString('member:3')]));
        self::assertSame([], $store->of('q', []));
        self::assertEquals([
            self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('q', 'b', 'member:1', 'k1', '2026-11-02 10:00'),
        ], $store->of('q', null, new DateTimeImmutable('2026-11-02 10:00 UTC'), new DateTimeImmutable('2026-11-03 10:00 UTC')));
        self::assertEquals([
            self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('q', 'b', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('r', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
        ], $store->ofMember(ResourceReference::fromString('member:1')));
    }

    public function testACancellationRemovesTheActivitysContributions(): void
    {
        $store = $this->store();
        $store->add(self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00'));
        $store->add(self::contribution('r', 'a', 'member:1', 'k1', '2026-11-02 10:00'));
        $store->add(self::contribution('q', 'a', 'member:1', 'k2', '2026-11-02 11:00'));

        self::assertEquals([
            self::contribution('q', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
            self::contribution('r', 'a', 'member:1', 'k1', '2026-11-02 10:00'),
        ], $store->remove('k1'));
        self::assertSame([], $store->remove('k1'));
        self::assertCount(1, $store->of('q'));
    }

    public function testAPeriodIsForgottenBeforeARebuild(): void
    {
        $store = $this->store();
        $store->add(self::contribution('q', 'a', 'member:1', 'k1', '2026-11-01 10:00'));
        $store->add(self::contribution('q', 'a', 'member:1', 'k2', '2026-11-02 10:00'));
        $store->add(self::contribution('q', 'a', 'member:1', 'k3', '2026-11-03 10:00'));

        self::assertSame(2, $store->forget(new DateTimeImmutable('2026-11-02 10:00 UTC')));
        self::assertCount(1, $store->of('q'));
        self::assertSame(1, $store->forget(null));
        self::assertSame([], $store->of('q'));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::contribution('q', 'a', 'member:1', 'k1', '2026-11-01 10:00'));
        $store->add(self::contribution('r', 'a', 'member:1', 'k1', '2026-11-01 10:00'));
        $store->add(self::contribution('q', 'a', 'member:2', 'k2', '2026-11-02 10:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->ofMember(ResourceReference::fromString('member:1')));
        self::assertCount(1, $store->of('q'));
    }

    protected static function contribution(string $quest, string $step, string $member, string $activity, string $at, int $value = 1): Contribution
    {
        return new Contribution($quest, $step, ResourceReference::fromString($member), $activity, $value, new DateTimeImmutable($at.' UTC'));
    }
}
