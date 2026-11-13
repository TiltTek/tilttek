<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\GameEngineException;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\Exception\IdentifyingSeat;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Application\SeatIdentifiers;
use Tilttek\GameEngineBundle\Tests\Fixture\RecordingLock;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

#[CoversClass(SeatIdentifiers::class)]
#[CoversClass(IdentifyingSeat::class)]
final class SeatIdentifiersTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function opaque(): iterable
    {
        foreach (['seat:1', 'seat-12', 'north', 'red', 'player.red', 'gm', 'ann', 'p1', 'user', 'members', 'mailbox:3', 'useful', 'seat.3', 'table:1.seat:2', 'Accounting', 'seat.com.au'] as $seat) {
            yield $seat => [$seat];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function identifying(): iterable
    {
        foreach (['ann@example.com', 'ann%40example.com', 'ann.example.com', 'jane.doe.gmail.com', 'ann_doe.example.CA', 'doe.qc', 'a.b.c.org', 'x.io'] as $seat) {
            yield $seat => [$seat, 'an email address'];
        }

        foreach (['member:44', 'member-44', 'member_44', 'member.44', 'member/44', 'member#44', 'member44', 'members:44', 'Member:ann', 'user:7', 'user7', 'users-7', 'account_12', 'customer:9', 'subscriber:3', 'profile:ann', 'person:2', 'uid42', 'uid:x', 'login:ann', 'email:3', 'e-mail:3', 'mail:3'] as $seat) {
            yield $seat => [$seat, 'a reference to a member or a user'];
        }
    }

    #[DataProvider('opaque')]
    #[DoesNotPerformAssertions]
    public function testAnOpaqueSeatIsAccepted(string $seat): void
    {
        SeatIdentifiers::check(['seat:0', $seat]);
    }

    #[DataProvider('identifying')]
    public function testASeatThatNamesAPersonIsRefused(string $seat, string $looksLike): void
    {
        try {
            SeatIdentifiers::check(['seat:0', $seat, 'seat:2']);
            self::fail(\sprintf('The seat "%s" was accepted.', $seat));
        } catch (IdentifyingSeat $identifyingSeat) {
            self::assertInstanceOf(GameEngineException::class, $identifyingSeat);
            self::assertSame(\sprintf('The seat "%s" looks like %s: a seat is an opaque identifier of the table, "seat:1", and the application keeps who sits there.', $seat, $looksLike), $identifyingSeat->getMessage());
        }
    }

    public function testATableIsNotOpenedWithASeatThatNamesAPerson(): void
    {
        $lock = new RecordingLock();
        $logs = new InMemoryLogStore();
        $tables = new GameTables(new Tables(new Games(new RollRace()), $logs), $lock);

        try {
            $tables->open('roll-race', 'table:1', ['seat:1', 'user:7']);
            self::fail('A seat that names a user was accepted.');
        } catch (IdentifyingSeat) {
            self::assertNull($logs->header('table:1'));
            self::assertSame([], $lock->held);
        }
    }
}
