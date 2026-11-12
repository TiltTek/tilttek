<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;

#[CoversClass(InMemoryOpeningStore::class)]
#[CoversClass(Opening::class)]
final class InMemoryOpeningStoreTest extends OpeningStoreTestCase
{
    #[Override]
    protected function store(): OpeningStore
    {
        return new InMemoryOpeningStore();
    }
}
