<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use JsonException;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Exception\BookingRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalSeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\Rows;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Rooms;
use Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure\Database;
use UnexpectedValueException;

#[CoversClass(DbalGameStore::class)]
#[CoversClass(DbalResultStore::class)]
#[CoversClass(DbalScreenStore::class)]
#[CoversClass(DbalSeatLedger::class)]
#[CoversClass(Rows::class)]
final class DbalStoresTest extends StoresTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = Database::connection();
    }

    public function testTheLedgerWritesTheLockAndThePositionOfEachBooking(): void
    {
        $ledger = $this->ledger();
        $accept = static fn (array $held): ?BookingRefusal => null;
        $ledger->hold(self::session('s-1'), new SeatBooking('a', 's-1', ResourceReference::of('member', 'ana'), 2, false), $accept);
        $ledger->hold(self::session('s-1'), new SeatBooking('b', 's-1', ResourceReference::of('member', 'ben'), 1, false), $accept);
        $ledger->hold(self::session('s-1'), new SeatBooking('c', 's-1', ResourceReference::of('member', 'cy'), 1, false), static fn (array $held): BookingRefusal => BookingRefusal::Full);

        self::assertSame([['session' => 's-1', 'locked_at' => '2026-11-14 20:00:00']], $this->connection->fetchAllAssociative('SELECT session, locked_at FROM '.EscapeRoomTables::SESSION_LOCKS));
        self::assertSame([['id' => 'a', 'room' => 'crypt', 'position' => 1], ['id' => 'b', 'room' => 'crypt', 'position' => 2]], array_map(
            static fn (array $row): array => ['id' => $row['id'], 'room' => $row['room'], 'position' => Rows::integer($row['position'])],
            $this->connection->fetchAllAssociative('SELECT id, room, position FROM '.EscapeRoomTables::BOOKINGS.' ORDER BY position'),
        ));
    }

    public function testALongIdentifierIsRefusedBeforeAnythingIsWritten(): void
    {
        $long = str_repeat('s', 256);

        try {
            $this->ledger()->hold(new Session($long, 'crypt', self::session('s')->startsAt, self::session('s')->endsAt), new SeatBooking('a', $long, ResourceReference::of('member', 'ana'), 1, false), static fn (array $held): ?BookingRefusal => null);
            self::fail('A session of 256 bytes is refused.');
        } catch (InvalidRoom $invalidRoom) {
            self::assertSame('The identifier of a session or a booking holds 255 bytes at most.', $invalidRoom->getMessage());
        }

        $this->expectException(InvalidRoom::class);

        $this->ledger()->hold(self::session('s'), new SeatBooking(str_repeat('b', 256), 's', ResourceReference::of('member', 'ana'), 1, false), static fn (array $held): ?BookingRefusal => null);
    }

    public function testTheIdentifiersOfTheLongestLengthAreKept(): void
    {
        $id = str_repeat('s', 255);
        $session = new Session($id, 'crypt', self::session('s')->startsAt, self::session('s')->endsAt);

        self::assertNull($this->ledger()->hold($session, new SeatBooking($id, $id, ResourceReference::of('member', 'ana'), 1, false), static fn (array $held): ?BookingRefusal => null));
        self::assertCount(1, $this->ledger()->bookings($session));
    }

    public function testTheReservationsOnPhilReservationHoldTheSeatsInTheDatabase(): void
    {
        $clock = new MockClock('2026-11-14 20:00:00 UTC');
        $booker = new SessionBooker(new BookableSessionReservations($this->ledger(), clock: $clock), clock: $clock);
        $room = new ConfiguredRoomCatalog(Rooms::config())->room('crypt');
        self::assertInstanceOf(Room::class, $room);

        $booker->book($room, self::session('s-1'), ResourceReference::of('member', 'ana'), 4, false, 'b-1');
        $booker->book($room, self::session('s-1'), ResourceReference::of('member', 'ben'), 2, false, 'b-2');
        self::assertSame(0, $booker->free($room, self::session('s-1')));

        $this->expectException(BookingRefused::class);

        $booker->book($room, self::session('s-1'), ResourceReference::of('member', 'cy'), 1, false, 'b-3');
    }

    public function testTheStateIsWrittenWithItsSlashesAndLettersAsTheyAre(): void
    {
        $values = [
            'room' => 'crypt',
            'team' => 'member:7',
            'session' => null,
            'progress' => ['note' => 'é/ü'],
        ];
        $this->games()->save('g-1', $values, null);

        self::assertSame('{"room":"crypt","team":"member:7","session":null,"progress":{"note":"é/ü"}}', $this->connection->fetchOne('SELECT state FROM '.EscapeRoomTables::GAMES.' WHERE id = ?', ['g-1']));

        $this->games()->eraseTeam(ResourceReference::of('member', '7'), ResourceReference::of('erased', 'abc'));

        self::assertSame('{"room":"crypt","team":"erased:abc","session":null,"progress":{"note":"é/ü"},"public":false}', $this->connection->fetchOne('SELECT state FROM '.EscapeRoomTables::GAMES.' WHERE id = ?', ['g-1']));
    }

    public function testAStateIsReadDownToThirtyTwoLevels(): void
    {
        $nested = static fn (int $levels): string => '{"progress":'.str_repeat('[', $levels).str_repeat(']', $levels).'}';
        $this->connection->insert(EscapeRoomTables::GAMES, ['id' => 'deep', 'room' => 'crypt', 'team' => 'team:a', 'state' => $nested(30)]);
        $this->connection->insert(EscapeRoomTables::GAMES, ['id' => 'deeper', 'room' => 'crypt', 'team' => 'team:a', 'state' => $nested(31)]);

        self::assertIsArray($this->games()->find('deep'));

        $this->expectException(JsonException::class);

        $this->games()->find('deeper');
    }

    public function testAMicrosecondIsWrittenAsAnInteger(): void
    {
        $this->results()->record('g-1', new RoomResult('crypt', ResourceReference::of('team', 'a'), null, 1, 1, 0, 0, 0, new DateTimeImmutable('2026-11-14 20:10:00.000125 UTC'), true));

        self::assertSame('integer', $this->connection->fetchOne('SELECT typeof(escaped_micro) FROM '.EscapeRoomTables::RESULTS));
        self::assertSame(125, Rows::integer($this->connection->fetchOne('SELECT escaped_micro FROM '.EscapeRoomTables::RESULTS)));
    }

    public function testTheLockOfASessionIsRenewedAlone(): void
    {
        $accept = static fn (array $held): ?BookingRefusal => null;
        $first = new DbalSeatLedger($this->connection, new MockClock('2026-11-14 20:00:00 UTC'));
        $first->hold(self::session('s-1'), new SeatBooking('a', 's-1', ResourceReference::of('member', 'ana'), 1, false), $accept);
        $first->hold(self::session('s-2'), new SeatBooking('b', 's-2', ResourceReference::of('member', 'ana'), 1, false), $accept);

        new DbalSeatLedger($this->connection, new MockClock('2026-11-14 21:00:00 UTC'))->hold(self::session('s-1'), new SeatBooking('c', 's-1', ResourceReference::of('member', 'ben'), 1, false), $accept);

        self::assertSame(
            [['session' => 's-1', 'locked_at' => '2026-11-14 21:00:00'], ['session' => 's-2', 'locked_at' => '2026-11-14 20:00:00']],
            $this->connection->fetchAllAssociative('SELECT session, locked_at FROM '.EscapeRoomTables::SESSION_LOCKS.' ORDER BY session'),
        );
    }

    public function testAMalformedStateIsRefused(): void
    {
        $this->connection->insert(EscapeRoomTables::GAMES, ['id' => 'g-1', 'room' => 'crypt', 'team' => 'team:a', 'state' => '"text"']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The state of a game is an object of values.');

        $this->games()->find('g-1');
    }

    public function testTheGamesForgottenAreDeletedFiveHundredAtATime(): void
    {
        $ids = array_map(static fn (int $n): string => 'g-'.$n, range(1, 1001));
        $sizes = [];

        self::assertSame(1001, new DbalGameStore(self::countingConnection($sizes))->forget(...$ids, ...\array_slice($ids, 0, 10)));
        self::assertSame([500, 500, 1], $sizes);
    }

    public function testTheScreensForgottenAreDeletedFiveHundredAtATime(): void
    {
        $games = array_map(static fn (int $n): string => 'g-'.$n, range(1, 1001));
        $sizes = [];

        self::assertSame(1001, new DbalScreenStore(self::countingConnection($sizes))->forget(...$games, ...\array_slice($games, 0, 10)));
        self::assertSame([500, 500, 1], $sizes);
    }

    /**
     * A connection that deletes nothing, and tells how many identifiers each statement was given.
     *
     * @param list<int> $sizes
     */
    private static function countingConnection(array &$sizes): Connection
    {
        $connection = self::createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use (&$sizes): int {
            $count = \is_array($params[0] ?? null) ? \count($params[0]) : 0;
            $sizes[] = $count;

            return $count;
        });

        return $connection;
    }

    #[Override]
    protected function games(): GameStore
    {
        return new DbalGameStore($this->connection);
    }

    #[Override]
    protected function results(): ResultStore
    {
        return new DbalResultStore($this->connection);
    }

    #[Override]
    protected function screens(): ScreenStore
    {
        return new DbalScreenStore($this->connection);
    }

    #[Override]
    protected function ledger(): SeatLedger&PartyBookings
    {
        return new DbalSeatLedger($this->connection, new MockClock('2026-11-14 20:00:00 UTC'));
    }
}
