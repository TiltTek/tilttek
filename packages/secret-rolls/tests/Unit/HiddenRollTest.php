<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(HiddenRoll::class)]
final class HiddenRollTest extends TestCase
{
    public function testAHiddenRollIsKeptInUtcAndReadsBack(): void
    {
        $opening = new Table()->rolls->roll('cave', '1d6')->opening;
        $hidden = new HiddenRoll($opening, 'gm', new DateTimeImmutable('2026-11-12 15:00:00', new DateTimeZone('America/Toronto')));

        self::assertSame('UTC', $hidden->rolledAt->getTimezone()->getName());
        self::assertSame(['opening' => $opening->toArray(), 'roller' => 'gm', 'rolled_at' => '2026-11-12T20:00:00.000000Z'], $hidden->toArray());
        self::assertEquals($hidden, HiddenRoll::fromArray($hidden->toArray()));
        self::assertEquals(new HiddenRoll($opening, null, Table::at()), HiddenRoll::fromArray(new HiddenRoll($opening, null, Table::at())->toArray()));
        self::assertSame($opening->roll, $hidden->roll());
        self::assertSame('cave', $hidden->scene());
        self::assertEquals($opening->commitment(), $hidden->commitment());
    }

    public function testDataThatDoesNotReadIsRefused(): void
    {
        $good = new HiddenRoll(new Table()->rolls->roll('cave', '1d6')->opening, 'gm', Table::at())->toArray();

        foreach ([['opening' => 'x'] + $good, ['roller' => 3] + $good, ['rolled_at' => null] + $good, ['roller' => ''] + $good, ['rolled_at' => 'now'] + $good, ['opening' => []] + $good] as $bad) {
            try {
                HiddenRoll::fromArray($bad);
                self::fail('A hidden roll that does not read was read.');
            } catch (InvalidSecretRoll) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectExceptionMessageIsOrContains('A hidden roll holds an opening');

        HiddenRoll::fromArray([]);
    }
}
