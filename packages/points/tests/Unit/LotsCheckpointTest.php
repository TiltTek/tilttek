<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Lot;
use Tilttek\Points\LotsCheckpoint;
use Tilttek\Points\MovementKey;

#[CoversClass(LotsCheckpoint::class)]
final class LotsCheckpointTest extends TestCase
{
    public function testACheckpointAsPlainValuesAndBack(): void
    {
        $checkpoint = new LotsCheckpoint(Account::fromString('member:member:1'), 'stars', 12, [
            new Lot(MovementKey::fromString('a'), new DateTimeImmutable('2026-01-10 08:00:00.5-05:00'), 3, new DateTimeImmutable('2026-02-10')),
            new Lot(MovementKey::fromString('#earn:b'), new DateTimeImmutable('2026-01-11 00:00:00 UTC'), 1, null),
        ], 0);
        $data = $checkpoint->toArray();

        self::assertSame(['debt' => 0, 'lots' => [
            ['key' => 'a', 'credited_at' => '2026-01-10T08:00:00.500000-05:00', 'remaining' => 3],
            ['key' => '#earn:b', 'credited_at' => '2026-01-11T00:00:00.000000+00:00', 'remaining' => 1],
        ]], $data);

        $decoded = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 8, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $read = LotsCheckpoint::fromArray(Account::fromString('member:member:1'), 'stars', 12, $decoded);
        self::assertSame($data, $read->toArray());
        self::assertSame(['member:member:1', 'stars', 12, 0], [$read->account->toString(), $read->currency, $read->position, $read->debt]);
        self::assertNull($read->lots[0]->expiresAt);
        self::assertSame('2026-01-10T13:00:00.500000+00:00', $read->lots[0]->creditedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP'));
        self::assertSame(['debt' => 4, 'lots' => []], LotsCheckpoint::fromArray(Account::fromString('member:member:1'), 'stars', 1, ['lots' => [], 'debt' => 4])->toArray());
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function invalidCheckpoints(): iterable
    {
        yield 'position 0' => [0, 0, 1, 'A checkpoint stands at a position from 1 with a debt from 0; 0 and 0 are not.'];
        yield 'negative debt' => [1, -1, 1, 'A checkpoint stands at a position from 1 with a debt from 0; 1 and -1 are not.'];
        yield 'empty lot' => [1, 0, 0, 'A checkpoint keeps the lots with points left; "a" has none.'];
    }

    #[DataProvider('invalidCheckpoints')]
    public function testAnInvalidCheckpoint(int $position, int $debt, int $remaining, string $message): void
    {
        $this->expectExceptionObject(InvalidPoints::because($message));
        new LotsCheckpoint(Account::fromString('member:member:1'), 'stars', $position, [new Lot(MovementKey::fromString('a'), new DateTimeImmutable(), $remaining, null)], $debt);
    }

    public function testTheCurrencyIsChecked(): void
    {
        $this->expectException(InvalidPoints::class);
        new LotsCheckpoint(Account::fromString('member:member:1'), 'Stars', 1);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function unreadableData(): iterable
    {
        $lots = 'A lot of a checkpoint holds its key, the moment of its credit and what is left of it.';

        yield 'no debt' => [['lots' => []], 'A checkpoint holds its debt and its lots.'];
        yield 'debt as text' => [['debt' => '0', 'lots' => []], 'A checkpoint holds its debt and its lots.'];
        yield 'no lots' => [['debt' => 0], 'A checkpoint holds its debt and its lots.'];
        yield 'lots as text' => [['debt' => 0, 'lots' => 'x'], 'A checkpoint holds its debt and its lots.'];
        yield 'lot as text' => [['debt' => 0, 'lots' => ['x']], $lots];
        yield 'no key' => [['debt' => 0, 'lots' => [['credited_at' => '2026-01-10T00:00:00.000000+00:00', 'remaining' => 1]]], $lots];
        yield 'no moment' => [['debt' => 0, 'lots' => [['key' => 'a', 'remaining' => 1]]], $lots];
        yield 'no remaining' => [['debt' => 0, 'lots' => [['key' => 'a', 'credited_at' => '2026-01-10T00:00:00.000000+00:00']]], $lots];
        yield 'remaining as text' => [['debt' => 0, 'lots' => [['key' => 'a', 'credited_at' => '2026-01-10T00:00:00.000000+00:00', 'remaining' => '1']]], $lots];
        yield 'moment without microseconds' => [['debt' => 0, 'lots' => [['key' => 'a', 'credited_at' => '2026-01-10T00:00:00+00:00', 'remaining' => 1]]], '"2026-01-10T00:00:00+00:00" is not the moment of a credit, as a checkpoint writes it.'];
        yield 'moment that overflows' => [['debt' => 0, 'lots' => [['key' => 'a', 'credited_at' => '2026-02-30T00:00:00.000000+00:00', 'remaining' => 1]]], '"2026-02-30T00:00:00.000000+00:00" is not the moment of a credit, as a checkpoint writes it.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('unreadableData')]
    public function testDataThatDoesNotReadAsACheckpoint(array $data, string $message): void
    {
        $this->expectExceptionObject(InvalidPoints::because($message));
        LotsCheckpoint::fromArray(Account::fromString('member:member:1'), 'stars', 1, $data);
    }
}
