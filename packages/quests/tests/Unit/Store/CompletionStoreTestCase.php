<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Store\CompletionStore;

/**
 * The contract every completion store passes: extend it with the store to test.
 */
abstract class CompletionStoreTestCase extends TestCase
{
    abstract protected function store(): CompletionStore;

    public function testAQuestIsAccomplishedOncePerOccurrenceAndSubject(): void
    {
        $store = $this->store();
        $member = ResourceReference::fromString('member:1');

        self::assertTrue($store->add(self::completion('q', '2025-12-01', 'member:1', '2025-12-03 10:00')));
        self::assertFalse($store->add(self::completion('q', '2025-12-01', 'member:1', '2025-12-04 10:00')));
        self::assertTrue($store->add(self::completion('q', '2026-12-01', 'member:1', '2026-12-02 10:00')));
        self::assertTrue($store->add(self::completion('r', '', 'member:1', '2026-01-02 10:00')));
        self::assertTrue($store->add(self::completion('q', '2025-12-01', 'member:2', '2025-12-02 10:00')));

        self::assertEquals(self::completion('q', '2025-12-01', 'member:1', '2025-12-03 10:00'), $store->find('q', '2025-12-01', $member));
        self::assertNull($store->find('q', '2024-12-01', $member));
        self::assertEquals(self::completion('q', '2026-12-01', 'member:1', '2026-12-02 10:00'), $store->latest('q', $member));
        self::assertNull($store->latest('s', $member));
        self::assertEquals([
            self::completion('q', '2025-12-01', 'member:1', '2025-12-03 10:00'),
            self::completion('r', '', 'member:1', '2026-01-02 10:00'),
            self::completion('q', '2026-12-01', 'member:1', '2026-12-02 10:00'),
        ], $store->ofSubject($member));
    }

    public function testAPeriodIsForgottenBeforeARebuild(): void
    {
        $store = $this->store();
        $store->add(self::completion('q', '', 'member:1', '2026-11-01 10:00'));
        $store->add(self::completion('s', '', 'member:1', '2026-10-01 10:00'));
        $store->add(self::completion('q', '', 'member:2', '2026-11-02 10:00'));
        $store->add(self::completion('r', '', 'member:1', '2026-11-03 10:00'));

        self::assertEquals([
            self::completion('q', '', 'member:2', '2026-11-02 10:00'),
            self::completion('r', '', 'member:1', '2026-11-03 10:00'),
        ], $store->forget(new DateTimeImmutable('2026-11-02 10:00 UTC')));
        self::assertCount(2, $store->ofSubject(ResourceReference::fromString('member:1')));
        self::assertEquals([
            self::completion('s', '', 'member:1', '2026-10-01 10:00'),
            self::completion('q', '', 'member:1', '2026-11-01 10:00'),
        ], $store->forget(null));
        self::assertSame([], $store->ofSubject(ResourceReference::fromString('member:1')));
    }

    public function testASubjectIsErased(): void
    {
        $store = $this->store();
        $store->add(self::completion('q', '', 'member:1', '2026-11-01 10:00'));
        $store->add(self::completion('r', '', 'member:1', '2026-11-01 10:00'));
        $store->add(self::completion('q', '', 'member:2', '2026-11-02 10:00'));

        self::assertSame(2, $store->eraseSubject(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->ofSubject(ResourceReference::fromString('member:1')));
        self::assertCount(1, $store->ofSubject(ResourceReference::fromString('member:2')));
    }

    protected static function completion(string $quest, string $occurrence, string $subject, string $at): Completion
    {
        return new Completion($quest, $occurrence, ResourceReference::fromString($subject), new DateTimeImmutable($at.' UTC'), 'activity:'.$at);
    }
}
