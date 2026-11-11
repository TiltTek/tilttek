<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Transfer;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\TransferLog;

#[CoversClass(InMemoryTransferLog::class)]
#[CoversClass(Erasure::class)]
final class InMemoryTransferLogTest extends TransferLogTestCase
{
    #[Override]
    protected function log(): TransferLog
    {
        return new InMemoryTransferLog();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryTransferLog();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryTransferLog(), $store);
    }
}
