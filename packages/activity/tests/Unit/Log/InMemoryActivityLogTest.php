<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit\Log;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Log\Reading;

#[CoversClass(InMemoryActivityLog::class)]
#[CoversClass(Reading::class)]
#[CoversClass(IdempotencyConflict::class)]
final class InMemoryActivityLogTest extends ActivityLogTestCase
{
    #[Override]
    protected function log(): ActivityLog
    {
        return new InMemoryActivityLog();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryActivityLog();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryActivityLog(), $store);
    }
}
