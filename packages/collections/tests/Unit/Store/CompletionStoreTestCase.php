<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Store;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\SetCompletion;
use Tilttek\Collections\Store\CompletionStore;

/**
 * The contract every completion store passes.
 */
abstract class CompletionStoreTestCase extends TestCase
{
    abstract protected function store(): CompletionStore;

    public function testASetIsCompletedOncePerMember(): void
    {
        $store = $this->store();
        $one = ResourceReference::fromString('member:1');

        self::assertTrue($store->add(new SetCompletion('crew', $one, new DateTimeImmutable('2026-11-08 12:00:00 UTC'))));
        self::assertFalse($store->add(new SetCompletion('crew', $one, new DateTimeImmutable('2026-11-09 12:00:00 UTC'))));
        self::assertTrue($store->add(new SetCompletion('ember', $one, new DateTimeImmutable('2026-11-07 12:00:00 UTC'))));
        self::assertTrue($store->add(new SetCompletion('dice', $one, new DateTimeImmutable('2026-11-07 12:00:00 UTC'))));
        self::assertTrue($store->add(new SetCompletion('crew', ResourceReference::fromString('member:2'), new DateTimeImmutable('2026-11-01 12:00:00 UTC'))));

        $completions = $store->ofMember($one);
        self::assertSame(['dice', 'ember', 'crew'], array_map(static fn (SetCompletion $completion): string => $completion->set, $completions));
        self::assertSame('2026-11-08 12:00', $completions[2]->completedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
        self::assertSame('member:1', $completions[0]->member->toString());

        self::assertSame(3, $store->eraseMember($one));
        self::assertSame([], $store->ofMember($one));
        self::assertCount(1, $store->ofMember(ResourceReference::fromString('member:2')));
    }
}
