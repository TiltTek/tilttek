<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use JsonException;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalFeedbackStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalParticipationStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalSignalStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalTableStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\Rows;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetyTables;
use Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure\Database;
use UnexpectedValueException;

#[CoversClass(DbalTableStore::class)]
#[CoversClass(DbalParticipationStore::class)]
#[CoversClass(DbalSignalStore::class)]
#[CoversClass(DbalFeedbackStore::class)]
#[CoversClass(Rows::class)]
final class DbalStoresTest extends StoresTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = Database::connection();
    }

    public function testAJsonColumnIsReadToTheDepthOfThirtyTwo(): void
    {
        $nested = static fn (int $levels): string => str_repeat('{"a":', $levels).'1'.str_repeat('}', $levels);

        self::assertIsArray(Rows::decode($nested(31)));

        $this->expectException(JsonException::class);
        Rows::decode($nested(32));
    }

    public function testAnAnonymousSignalStoresNoAuthor(): void
    {
        $store = $this->signals();
        $store->add($this->signal('7', SignalKind::XCard, '20:00'));

        $row = $this->connection->fetchAssociative(\sprintf('SELECT * FROM %s', TableSafetyTables::SIGNALS));

        self::assertIsArray($row);
        self::assertNull($row['raised_by']);
        self::assertSame('table:7', $row['table_ref']);
        self::assertSame('x-card', $row['kind']);
        self::assertSame('2026-11-12 20:00:00', $row['raised_at']);
    }

    public function testSignalsOfTheSameSecondKeepTheirOrder(): void
    {
        $store = $this->signals();
        $table = ResourceReference::of('table', '7');
        $ids = [];

        foreach (['20:00:00.900000', '20:00:00.100000', '20:00:00.500000'] as $at) {
            $signal = new Signal(Signal::newId(), $table, SignalKind::Pause, Audience::Table, new DateTimeImmutable('2026-11-12 '.$at.' UTC'));
            $store->add($signal);
            $ids[$at] = $signal->id;
        }

        self::assertSame([$ids['20:00:00.100000'], $ids['20:00:00.500000'], $ids['20:00:00.900000']], array_map(static fn (Signal $signal): string => $signal->id, $store->recent($table)));
        self::assertSame([$ids['20:00:00.900000']], array_map(static fn (Signal $signal): string => $signal->id, $store->recent($table, new DateTimeImmutable('2026-11-12 20:00:00.500000 UTC'))));
        self::assertSame(1_794_513_600_500_000, Rows::microseconds(new DateTimeImmutable('2026-11-12 20:00:00.500000 UTC')));
    }

    public function testDatesAreWrittenInUtc(): void
    {
        $store = $this->signals();
        $signal = $this->signal('7', SignalKind::XCard, '20:00');
        $store->add($signal);
        $store->acknowledge($signal->table, $signal->id, new DateTimeImmutable('2026-11-12 16:30:00', new DateTimeZone('America/Toronto')));

        self::assertSame('2026-11-12 21:30:00', $this->connection->fetchOne(\sprintf('SELECT acknowledged_at FROM %s', TableSafetyTables::SIGNALS)));
        self::assertEquals(new DateTimeImmutable('2026-11-12 21:30:00 UTC'), $store->recent(ResourceReference::of('table', '7'))[0]->acknowledgedAt);
    }

    public function testRowsAreReadStrictly(): void
    {
        self::assertSame([], Rows::decode('{}'));
        self::assertSame(['a' => [1]], Rows::decode('{"a":[1]}'));
        self::assertSame('{}', Rows::json([]));
        self::assertSame('{"é":["x"]}', Rows::json(['é' => ['x']]));
        self::assertSame('UTC', Rows::utc(new DateTimeImmutable('2026-11-12 10:00', new DateTimeZone('Europe/Paris')))->getTimezone()->getName());
        self::assertSame('2026-11-12 09:00:00', Rows::utc(new DateTimeImmutable('2026-11-12 10:00', new DateTimeZone('Europe/Paris')))->format('Y-m-d H:i:s'));
        self::assertSame('UTC', Rows::date('2026-11-12 10:00:00')->getTimezone()->getName());

        $refused = [];

        foreach ([static fn (): mixed => Rows::text(1), static fn (): mixed => Rows::decode('"text"'), static fn (): mixed => Rows::decode(null)] as $read) {
            try {
                $read();
            } catch (UnexpectedValueException $unexpectedValueException) {
                $refused[] = $unexpectedValueException->getMessage();
            }
        }

        self::assertSame(['A text column holds int.', 'A JSON column holds no object.', 'A text column holds null.'], $refused);
    }

    #[Override]
    protected function tables(): TableStore
    {
        return new DbalTableStore($this->connection);
    }

    #[Override]
    protected function participations(): ParticipationStore
    {
        return new DbalParticipationStore($this->connection);
    }

    #[Override]
    protected function signals(): SignalStore
    {
        return new DbalSignalStore($this->connection);
    }

    #[Override]
    protected function feedback(): FeedbackStore
    {
        return new DbalFeedbackStore($this->connection);
    }
}
