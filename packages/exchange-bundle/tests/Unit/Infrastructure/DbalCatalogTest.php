<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\UnknownReward;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use UnexpectedValueException;

#[CoversClass(DbalCatalog::class)]
final class DbalCatalogTest extends TestCase
{
    public function testTheRewardsAreKeptAndReadBack(): void
    {
        $catalog = new DbalCatalog(Database::connection());
        $catalog->save(new Reward('seat', 250, 'stars', 30, new DateTimeImmutable('2026-11-01 00:00:00 America/Toronto'), new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 2, ['badge:helper', 'level:5']));
        $catalog->save(new Reward('discount-10', 100));

        $seat = $catalog->get('seat');
        self::assertSame(['seat', 250, 'stars', 30, '2026-11-01 04:00:00', '2026-12-01 00:00:00', 2, ['badge:helper', 'level:5']], [$seat->code, $seat->price, $seat->currency, $seat->stock, $seat->availableFrom?->format('Y-m-d H:i:s'), $seat->availableUntil?->format('Y-m-d H:i:s'), $seat->perMember, $seat->requires]);
        $discount = $catalog->get('discount-10');
        self::assertSame([null, null, null, null, null, []], [$discount->currency, $discount->stock, $discount->availableFrom, $discount->availableUntil, $discount->perMember, $discount->requires]);
        self::assertSame(['discount-10', 'seat'], array_map(static fn (Reward $reward): string => $reward->code, $catalog->all()));

        $catalog->save(new Reward('seat', 300));
        self::assertSame(300, $catalog->get('seat')->price);
        self::assertCount(2, $catalog->all());

        $catalog->remove('seat');
        self::assertSame(['discount-10'], array_map(static fn (Reward $reward): string => $reward->code, $catalog->all()));
    }

    public function testAnUnknownReward(): void
    {
        $this->expectExceptionObject(UnknownReward::named('seat'));

        new DbalCatalog(Database::connection())->get('seat');
    }

    public function testTheConditionsAreAListOfStrings(): void
    {
        $connection = Database::connection();
        $catalog = new DbalCatalog($connection);

        foreach (['"badge"' => ['badge'], '["a", "b"]' => ['a', 'b'], '{"x": "c"}' => ['c']] as $json => $requires) {
            $connection->executeStatement('DELETE FROM '.ExchangeTables::REWARDS);
            $connection->insert(ExchangeTables::REWARDS, ['code' => 'seat', 'price' => 1, 'requires' => $json]);
            self::assertSame($requires, $catalog->get('seat')->requires);
        }

        $connection->executeStatement('DELETE FROM '.ExchangeTables::REWARDS);
        $connection->insert(ExchangeTables::REWARDS, ['code' => 'seat', 'price' => 1, 'requires' => '[1]']);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The conditions of a reward are a JSON list of strings; "[1]" is not.');

        $catalog->get('seat');
    }
}
