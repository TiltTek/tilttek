<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\CopyStore;

/**
 * The contract every copy store passes.
 */
abstract class CopyStoreTestCase extends TestCase
{
    abstract protected function store(): CopyStore;

    public function testACopyIsWrittenOnce(): void
    {
        $store = $this->store();
        $copy = $this->copy('c1', 'member:1', '2026-11-07 12:00:00', tradable: false);

        self::assertTrue($store->add($copy));
        self::assertFalse($store->add($this->copy('c1', 'member:2', '2026-11-08 12:00:00')));
        self::assertNull($store->find('c2'));

        $found = $store->find('c1') ?? self::fail('Not found.');
        self::assertSame(['c1', 'pirate', 'member:1', Source::Gift, 'key:c1', false, false], [$found->id, $found->item, $found->owner->toString(), $found->source, $found->key, $found->tradable, $found->moved]);
        self::assertSame($copy->acquiredAt->getTimestamp(), $found->acquiredAt->getTimestamp());
    }

    public function testAMembersCopiesComeInOrder(): void
    {
        $store = $this->store();
        $store->add($this->copy('c3', 'member:1', '2026-11-07 12:00:00'));
        $store->add($this->copy('c1', 'member:1', '2026-11-08 12:00:00'));
        $store->add($this->copy('c2', 'member:1', '2026-11-07 12:00:00'));
        $store->add($this->copy('c4', 'member:2', '2026-11-01 12:00:00'));

        self::assertSame(['c2', 'c3', 'c1'], array_map(static fn (Copy $copy): string => $copy->id, $store->ofMember(ResourceReference::fromString('member:1'))));
        self::assertSame([], $store->ofMember(ResourceReference::fromString('member:3')));
    }

    public function testACopyIsReassignedFromItsOwnerOnly(): void
    {
        $store = $this->store();
        $store->add($this->copy('c1', 'member:1', '2026-11-07 12:00:00'));

        $one = ResourceReference::fromString('member:1');
        $two = ResourceReference::fromString('member:2');

        self::assertFalse($store->reassign('c1', $two, $one));
        self::assertFalse($store->reassign('c9', $one, $two));
        self::assertTrue($store->reassign('c1', $one, $two));

        $moved = $store->find('c1') ?? self::fail('Not found.');
        self::assertSame(['member:2', true], [$moved->owner->toString(), $moved->moved]);
        self::assertSame([], $store->ofMember($one));
    }

    public function testACopyIsTakenBackFromItsFirstMemberOnly(): void
    {
        $store = $this->store();
        $one = ResourceReference::fromString('member:1');
        $two = ResourceReference::fromString('member:2');
        $store->add($this->copy('c1', 'member:1', '2026-11-07 12:00:00'));
        $store->add($this->copy('c2', 'member:1', '2026-11-07 12:00:00'));
        $store->reassign('c2', $one, $two);
        $store->reassign('c2', $two, $one);

        self::assertFalse($store->takeBack('c1', $two));
        self::assertFalse($store->takeBack('c2', $one));
        self::assertFalse($store->takeBack('c9', $one));
        self::assertTrue($store->takeBack('c1', $one));
        self::assertNull($store->find('c1'));
        self::assertNotNull($store->find('c2'));
    }

    public function testCopiesOfASourceAreForgottenFromAMomentUnlessTheyMoved(): void
    {
        $store = $this->store();
        $store->add($this->copy('d1', 'member:1', '2026-11-06 12:00:00', Source::Daily));
        $store->add($this->copy('d2', 'member:1', '2026-11-07 12:00:00', Source::Daily));
        $store->add($this->copy('d3', 'member:1', '2026-11-08 12:00:00', Source::Daily));
        $store->add($this->copy('d4', 'member:1', '2026-11-08 12:00:00', Source::Daily));
        $store->add($this->copy('g1', 'member:1', '2026-11-08 12:00:00', Source::Gift));
        $store->reassign('d4', ResourceReference::fromString('member:1'), ResourceReference::fromString('member:2'));

        self::assertSame(2, $store->forget(Source::Daily, new DateTimeImmutable('2026-11-07 12:00:00 UTC')));
        self::assertSame(['d1', 'g1'], array_map(static fn (Copy $copy): string => $copy->id, $store->ofMember(ResourceReference::fromString('member:1'))));
        self::assertSame(1, $store->forget(Source::Daily, null));
        self::assertSame(0, $store->forget(Source::Pack, null));
        self::assertNotNull($store->find('d4'));
        self::assertNotNull($store->find('g1'));
    }

    public function testAnOwnersCopiesAreErased(): void
    {
        $store = $this->store();
        $store->add($this->copy('c1', 'member:1', '2026-11-07 12:00:00'));
        $store->add($this->copy('c2', 'member:1', '2026-11-07 12:00:00'));
        $store->add($this->copy('c3', 'member:2', '2026-11-07 12:00:00'));

        self::assertSame(2, $store->eraseOwner(ResourceReference::fromString('member:1')));
        self::assertSame(0, $store->eraseOwner(ResourceReference::fromString('member:1')));
        self::assertNotNull($store->find('c3'));
    }

    private function copy(string $id, string $owner, string $at, Source $source = Source::Gift, bool $tradable = true): Copy
    {
        return new Copy($id, 'pirate', ResourceReference::fromString($owner), $source, 'key:'.$id, new DateTimeImmutable($at.' UTC'), $tradable);
    }
}
