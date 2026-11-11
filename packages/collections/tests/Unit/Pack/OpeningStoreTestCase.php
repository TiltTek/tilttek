<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Pack;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\PackOpening;

/**
 * The contract every opening store passes.
 */
abstract class OpeningStoreTestCase extends TestCase
{
    abstract protected function store(): OpeningStore;

    public function testAnOpeningIsWrittenOncePerKey(): void
    {
        $store = $this->store();
        $opening = $this->opening('k2', 'member:1', '2026-11-07 12:00:00');

        self::assertTrue($store->add($opening));
        self::assertFalse($store->add($this->opening('k2', 'member:2', '2026-11-08 12:00:00')));
        self::assertTrue($store->add($this->opening('k1', 'member:1', '2026-11-07 12:00:00')));
        self::assertTrue($store->add($this->opening('k0', 'member:1', '2026-11-08 12:00:00')));
        self::assertTrue($store->add($this->opening('k3', 'member:2', '2026-11-01 12:00:00')));
        self::assertNull($store->find('k9'));

        $found = $store->find('k2') ?? self::fail('Not found.');
        self::assertSame($opening->toArray(), [...$found->toArray(), 'opened_at' => $opening->openedAt->format(DateTimeInterface::ATOM)]);
        self::assertSame($opening->openedAt->getTimestamp(), $found->openedAt->getTimestamp());
        self::assertSame(['k1', 'k2', 'k0'], array_map(static fn (PackOpening $one): string => $one->key, $store->ofMember(ResourceReference::fromString('member:1'))));

        self::assertSame(3, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->ofMember(ResourceReference::fromString('member:1')));
        self::assertNotNull($store->find('k3'));
    }

    private function opening(string $key, string $member, string $at): PackOpening
    {
        return new PackOpening($key, ResourceReference::fromString($member), 'booster', str_repeat('ab', 16), str_repeat('c', 64), 'mine', ['pirate', 'deckhand'], ['c1', 'c2'], new DateTimeImmutable($at.' UTC'));
    }
}
