<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Choice\OwnedGame;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\PlayedGame;
use Tilttek\GameNight\Registration;
use Tilttek\GameNight\RegistrationStatus;
use Tilttek\GameNight\Table;

#[CoversClass(Table::class)]
#[CoversClass(Registration::class)]
#[CoversClass(PlayedGame::class)]
#[CoversClass(OwnedGame::class)]
final class ValuesTest extends TestCase
{
    public function testATableHasItsSeatsGameAndLeader(): void
    {
        $table = new Table('t-1', Table::MAX_SEATS, 'g@1', 'm-1');

        self::assertSame('t-1', $table->id);
        self::assertSame(30, $table->seats);
        self::assertSame('g@1', $table->game);
        self::assertSame('m-1', $table->leader);
        self::assertSame(1, new Table('t', 1)->seats);
    }

    public function testARegistrationMovesBetweenTablesAndStatuses(): void
    {
        $waiting = new Registration('m-1', true, RegistrationStatus::Waitlisted);

        self::assertFalse($waiting->isConfirmed());
        self::assertTrue($waiting->confirmed()->isConfirmed());
        self::assertTrue($waiting->confirmed()->guest);
        self::assertSame('t-1', $waiting->confirmed()->seatedAt('t-1')->table);
        self::assertSame('t-1', $waiting->seatedAt('t-1')->confirmed()->table);
        self::assertSame(RegistrationStatus::Waitlisted, $waiting->seatedAt('t-1')->status);
        self::assertTrue($waiting->seatedAt('t-1')->guest);
        self::assertSame('m-1', $waiting->seatedAt(null)->member);
        self::assertSame(1, new Registration('m-2')->places());
        self::assertSame(RegistrationStatus::Confirmed, new Registration('m-2')->status);
    }

    public function testAPlayedGameKeepsItsValues(): void
    {
        $play = new PlayedGame('n-1', 't-1', 'g-1', ['m-1', 'm-2'], 1, new DateTimeImmutable('2027-03-05 20:00'), 45);

        self::assertSame(['n-1', 't-1', 'g-1'], [$play->night, $play->table, $play->game]);
        self::assertSame(['m-1', 'm-2'], $play->players);
        self::assertSame(1, $play->guests);
        self::assertSame(45, $play->minutes);
        self::assertSame(1, new PlayedGame('n', 't', 'g', ['m'], 0, new DateTimeImmutable(), 1)->minutes);
        self::assertSame(29, new PlayedGame('n', 't', 'g', ['m'], 29, new DateTimeImmutable(), 1440)->guests);
    }

    public function testAnOwnedGameHasItsOwner(): void
    {
        $owned = new OwnedGame('g@1', 'm-1');

        self::assertSame('g@1', $owned->game);
        self::assertSame('m-1', $owned->owner);
    }

    /**
     * @return iterable<string, array{Closure(): object, string}>
     */
    public static function invalidValues(): iterable
    {
        $at = new DateTimeImmutable('2027-03-05 20:00');

        yield 'table id' => [static fn (): object => new Table('', 2), 'The identifier of a table'];
        yield 'no seat' => [static fn (): object => new Table('t', 0), 'A table has 1 to 30 seats.'];
        yield 'too many seats' => [static fn (): object => new Table('t', 31), 'A table has 1 to 30 seats.'];
        yield 'table game' => [static fn (): object => new Table('t', 2, '9'), 'The identifier of a game'];
        yield 'table leader' => [static fn (): object => new Table('t', 2, null, 'm@x'), 'The identifier of a member'];
        yield 'member' => [static fn (): object => new Registration('a@b.c'), 'The identifier of a member'];
        yield 'seat' => [static fn (): object => new Registration('m', false, RegistrationStatus::Confirmed, 't t'), 'The identifier of a table'];
        yield 'owned game' => [static fn (): object => new OwnedGame('-', 'm'), 'The identifier of a game'];
        yield 'owner' => [static fn (): object => new OwnedGame('g', '-'), 'The identifier of a member'];
        yield 'play night' => [static fn (): object => new PlayedGame('-', 't', 'g', ['m'], 0, $at), 'The identifier of a night'];
        yield 'play table' => [static fn (): object => new PlayedGame('n', '-', 'g', ['m'], 0, $at), 'The identifier of a table'];
        yield 'play game' => [static fn (): object => new PlayedGame('n', 't', '-', ['m'], 0, $at), 'The identifier of a game'];
        yield 'play player' => [static fn (): object => new PlayedGame('n', 't', 'g', ['-'], 0, $at), 'The identifier of a member'];
        yield 'no player' => [static fn (): object => new PlayedGame('n', 't', 'g', [], 0, $at), 'A game is played by at least one member, each once.'];
        yield 'player twice' => [static fn (): object => new PlayedGame('n', 't', 'g', ['m', 'm'], 0, $at), 'A game is played by at least one member, each once.'];
        yield 'negative guests' => [static fn (): object => new PlayedGame('n', 't', 'g', ['m'], -1, $at), 'A game is played by at most 30 players, guests included.'];
        yield 'too many players' => [static fn (): object => new PlayedGame('n', 't', 'g', ['m'], 30, $at), 'A game is played by at most 30 players, guests included.'];
        yield 'no minute' => [static fn (): object => new PlayedGame('n', 't', 'g', ['m'], 0, $at, 0), 'A game lasts 1 to 1440 minutes.'];
        yield 'too many minutes' => [static fn (): object => new PlayedGame('n', 't', 'g', ['m'], 0, $at, 1441), 'A game lasts 1 to 1440 minutes.'];
    }

    /**
     * @param Closure(): object $build
     */
    #[DataProvider('invalidValues')]
    public function testAnInvalidValueIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
