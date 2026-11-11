<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Store\GrantStore;

/**
 * The contract of a `GrantStore`, which every implementation passes.
 */
abstract class GrantStoreTestCase extends TestCase
{
    public function testAConsentIsGivenAndWithdrawn(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        self::assertFalse($store->allows($member, 'portfolio'));
        self::assertSame([], $store->clients($member));

        $store->grant($member, 'portfolio', new DateTimeImmutable('2026-11-01 10:00:00 UTC'));
        $store->grant($member, 'portfolio', new DateTimeImmutable('2026-11-02 10:00:00 UTC'));
        $store->grant($member, 'archive', new DateTimeImmutable('2026-11-02 10:00:00 UTC'));
        $store->grant(ResourceReference::of('member', '2'), 'other', new DateTimeImmutable('2026-11-02 10:00:00 UTC'));

        self::assertTrue($store->allows($member, 'portfolio'));
        self::assertFalse($store->allows($member, 'other'));
        self::assertSame(['archive', 'portfolio'], $store->clients($member));

        self::assertTrue($store->withdraw($member, 'portfolio'));
        self::assertFalse($store->withdraw($member, 'portfolio'));
        self::assertFalse($store->allows($member, 'portfolio'));
        self::assertSame(['archive'], $store->clients($member));
        self::assertTrue($store->allows(ResourceReference::of('member', '2'), 'other'));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->grant($member, 'portfolio', new DateTimeImmutable('2026-11-01 10:00:00 UTC'));
        $store->grant($member, 'archive', new DateTimeImmutable('2026-11-01 10:00:00 UTC'));
        $store->grant(ResourceReference::of('member', '2'), 'archive', new DateTimeImmutable('2026-11-01 10:00:00 UTC'));

        self::assertSame(2, $store->eraseMember($member));
        self::assertSame([], $store->clients($member));
        self::assertSame(0, $store->eraseMember($member));
        self::assertSame(['archive'], $store->clients(ResourceReference::of('member', '2')));
    }

    abstract protected function store(): GrantStore;
}
