<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit\Ledger;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\Ledger\Reading;

#[CoversClass(InMemoryLedger::class)]
#[CoversClass(Reading::class)]
#[CoversClass(MovementConflict::class)]
#[CoversClass(InsufficientPoints::class)]
final class InMemoryLedgerTest extends LedgerTestCase
{
    #[Override]
    protected function ledger(): Ledger
    {
        return new InMemoryLedger();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryLedger();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryLedger(), $store);
    }
}
