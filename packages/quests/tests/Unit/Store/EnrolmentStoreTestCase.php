<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Store\EnrolmentStore;

/**
 * The contract every enrolment store passes: extend it with the store to test.
 */
abstract class EnrolmentStoreTestCase extends TestCase
{
    abstract protected function store(): EnrolmentStore;

    public function testAnEnrolmentIsKeptPerQuestAndMember(): void
    {
        $store = $this->store();
        $first = self::enrolment('q', 'member:2', '2026-11-01 10:00');
        $team = new Enrolment('raid', ResourceReference::fromString('member:2'), new DateTimeImmutable('2026-11-01 09:00 UTC'), ResourceReference::fromString('team:red'));

        self::assertNull($store->find('q', ResourceReference::fromString('member:2')));
        $store->save($first);
        $store->save(self::enrolment('q', 'member:1', '2026-11-01 11:00'));
        $store->save($team);
        self::assertEquals($first, $store->find('q', ResourceReference::fromString('member:2')));

        $abandoned = $first->abandon(new DateTimeImmutable('2026-11-02 10:00 UTC'));
        $store->save($abandoned);
        self::assertEquals($abandoned, $store->find('q', ResourceReference::fromString('member:2')));
        self::assertEquals([self::enrolment('q', 'member:1', '2026-11-01 11:00'), $abandoned], $store->ofQuest('q'));
        self::assertEquals([$abandoned, $team], $store->ofMember(ResourceReference::fromString('member:2')));
        self::assertNull($store->find('raid', ResourceReference::fromString('member:1')));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->save(self::enrolment('q', 'member:1', '2026-11-01 10:00'));
        $store->save(self::enrolment('r', 'member:1', '2026-11-01 10:00'));
        $store->save(self::enrolment('q', 'member:2', '2026-11-01 10:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->ofMember(ResourceReference::fromString('member:1')));
        self::assertCount(1, $store->ofQuest('q'));
    }

    protected static function enrolment(string $quest, string $member, string $at): Enrolment
    {
        return new Enrolment($quest, ResourceReference::fromString($member), new DateTimeImmutable($at.' UTC'));
    }
}
