<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\UtcClock;

#[CoversClass(InMemoryCompletionStore::class)]
#[CoversClass(InMemoryDefinitionStore::class)]
#[CoversClass(Completion::class)]
#[CoversClass(UtcClock::class)]
final class StoresTest extends TestCase
{
    public function testTheDefinitionStoreKeepsEveryVersion(): void
    {
        $store = new InMemoryDefinitionStore();

        self::assertNull($store->latest('none'));
        self::assertTrue($store->add(new SetDefinition('s', 2, ['a'])));
        self::assertTrue($store->add(new SetDefinition('s', 10, ['a', 'b'])));
        self::assertTrue($store->add(new SetDefinition('s', 3, ['c'])));
        self::assertFalse($store->add(new SetDefinition('s', 2, ['z'])));
        self::assertSame(10, $store->latest('s')?->version);
        self::assertSame(['a'], $store->version('s', 2)?->members);
        self::assertNull($store->version('s', 4));
        self::assertNull($store->version('t', 2));
    }

    public function testTheCompletionStoreKeepsOnePerSetTierAndHolder(): void
    {
        $store = new InMemoryCompletionStore();
        $one = ResourceReference::of('member', '1');
        $two = ResourceReference::of('member', '2');
        $at = new DateTimeImmutable('2026-11-13 12:00:00 UTC');

        self::assertTrue($store->add(new Completion('s', $one, null, 1, $at)));
        self::assertFalse($store->add(new Completion('s', $one, null, 2, $at)));
        self::assertTrue($store->add(new Completion('s', $one, 'gold', 1, $at)));
        self::assertTrue($store->add(new Completion('t', $one, null, 1, $at)));
        self::assertTrue($store->add(new Completion('s', $two, null, 1, $at)));

        self::assertSame([null, 'gold'], array_map(static fn (Completion $completion): ?string => $completion->tier, $store->of($one, 's')));
        self::assertSame(1, $store->of($one, 's')[0]->version);
        self::assertSame(['s', 's', 't'], array_map(static fn (Completion $completion): string => $completion->set, $store->ofHolder($one)));
        self::assertSame([0, 1, 2], array_keys($store->ofHolder($one)));
        self::assertSame([0, 1], array_keys($store->of($one, 's')));
        self::assertCount(1, $store->of($two, 's'));
        self::assertSame(3, $store->eraseHolder($one));
        self::assertSame([], $store->ofHolder($one));
        self::assertCount(1, $store->ofHolder($two));
    }

    public function testACompletionIsInUtcAndKeyedBySetTierAndHolder(): void
    {
        $holder = ResourceReference::of('member', 'x:1');
        $completion = new Completion('s', $holder, 'gold', 3, new DateTimeImmutable('2026-11-13 09:30:00.5-05:00'));

        self::assertSame('UTC', $completion->at->getTimezone()->getName());
        self::assertSame(['set' => 's', 'holder' => 'member:x:1', 'tier' => 'gold', 'version' => 3, 'at' => '2026-11-13T14:30:00.500000Z'], $completion->toArray());
        self::assertSame("s\0gold\0member:x:1", $completion->key());
        self::assertFalse($completion->isOfSet());

        $own = new Completion('s', $holder, null, 1, new DateTimeImmutable());
        self::assertTrue($own->isOfSet());
        self::assertSame("s\0\0member:x:1", $own->key());
    }

    public function testACompletionOfAnInvalidTierOrHolderIsRefused(): void
    {
        try {
            new Completion('s', ResourceReference::of('member', 'a@b'), null, 1, new DateTimeImmutable());
            self::fail('No exception.');
        } catch (InvalidSet) {
        }

        try {
            new Completion('S', ResourceReference::of('member', '1'), null, 1, new DateTimeImmutable());
            self::fail('No exception.');
        } catch (InvalidSet) {
        }

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The code of a tier is');

        new Completion('s', ResourceReference::of('member', '1'), 'Gold', 1, new DateTimeImmutable());
    }

    public function testTheUtcClockGivesTheTimeInUtc(): void
    {
        $before = time();
        $now = new UtcClock()->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
