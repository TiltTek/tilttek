<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Memory\InMemoryOpenings;

#[CoversClass(InMemoryOpenings::class)]
final class InMemoryOpeningsTest extends OpeningStoreTestCase
{
    public function testTheOpeningsAreForgottenAfterARequest(): void
    {
        $store = new InMemoryOpenings();
        $ann = ResourceReference::of('member', '44');
        $store->add(self::opening('advent', $ann, 1, '2026-12-01 10:00:00'));
        $store->add(self::opening('advent', $ann, 2, '2026-12-02 10:00:00'));

        self::assertSame(['advent'], array_unique(array_map(static fn (Opening $opening): string => $opening->calendar, $store->ofMember($ann))));
        self::assertCount(2, $store->ofMember($ann));

        $store->reset();

        self::assertNull($store->find('advent', $ann, 1));
        self::assertSame([], $store->ofMember($ann));
        self::assertTrue($store->add(self::opening('advent', $ann, 1, '2026-12-01 10:00:00')));
    }

    #[Override]
    protected function store(): OpeningStore&MemberOpenings
    {
        return new InMemoryOpenings();
    }
}
