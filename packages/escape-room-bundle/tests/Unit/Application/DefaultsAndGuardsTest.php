<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Exception\DeskRefused;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;
use Tilttek\EscapeRoomBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDueHandler;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Desk;

/**
 * The defaults of the desk, the handler and the command, and what the desk refuses before it reads a store.
 */
#[CoversClass(GameDesk::class)]
#[CoversClass(Screen::class)]
#[CoversClass(DeskRefused::class)]
#[CoversClass(ExpireDueHandler::class)]
#[CoversClass(ExpireCommand::class)]
final class DefaultsAndGuardsTest extends TestCase
{
    public function testTheDeskEndsAHundredGamesAtMostByDefault(): void
    {
        $kit = $this->dueGames(101);

        $first = $kit->desk->expireDue();
        $rest = $kit->desk->expireDue();

        self::assertCount(100, $first);
        self::assertSame(['g-100'], $rest);
    }

    public function testTheHandlerEndsAHundredGamesAtMostByDefault(): void
    {
        $kit = $this->dueGames(101);

        self::assertCount(100, new ExpireDueHandler($kit->desk)());
    }

    public function testTheCommandEndsAHundredGamesAtMostByDefault(): void
    {
        $kit = $this->dueGames(101);
        $application = new Application();
        $application->addCommand(new ExpireCommand($kit->desk));

        $tester = new CommandTester($application->find('tilttek:escape-room:expire'));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('[OK] 100 game(s) ended.', $tester->getDisplay());
    }

    public function testSeveralGamesEndedAreAllTold(): void
    {
        $kit = $this->dueGames(3);

        self::assertSame(['g-0', 'g-1', 'g-2'], $kit->desk->expireDue());
    }

    public function testTheLeaderboardShowsTenPlacesByDefault(): void
    {
        $kit = new Desk();

        for ($index = 0; $index < 11; ++$index) {
            $kit->desk->start('g-'.$index, 'cell', ResourceReference::of('team', 't'.$index));
            $kit->clock->sleep(1);
            $kit->desk->guess('g-'.$index, 'door', 'key');
            $kit->desk->consent('g-'.$index, true);
        }

        self::assertCount(10, $kit->desk->leaderboard('cell'));
        self::assertCount(11, $kit->desk->leaderboard('cell', 11));
        self::assertCount(1, $kit->desk->leaderboard('cell', 1));
    }

    #[DataProvider('refusedLimits')]
    public function testALeaderboardOutOfBoundsIsRefusedBeforeTheStoreIsRead(int $limit): void
    {
        $results = $this->createMock(ResultStore::class);
        $results->expects(self::never())->method('shown');
        $kit = new Desk(results: $results);

        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A leaderboard shows 1 to 1000 places.');

        $kit->desk->leaderboard('cell', $limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'none' => [0];
        yield 'over' => [1_001];
    }

    public function testAnErasedHostCannotClearTheScreen(): void
    {
        $kit = new Desk();
        $kit->desk->start('g-1', 'cell', ResourceReference::of('team', 'owls'));

        try {
            $kit->desk->clearScreen('g-1', ResourceReference::of('erased', 'abc'));
            self::fail('An erased host is refused.');
        } catch (InvalidRoom) {
            self::assertNull($kit->desk->screen('g-1'));
            self::assertSame(['g-1:start'], $kit->publisher->moves);
        }
    }

    public function testTheIdentifierInARefusalIsCutToSixtyFourCharacters(): void
    {
        $long = str_repeat('é', 70);

        self::assertSame('No game "'.str_repeat('é', 64).'".', DeskRefused::unknownGame($long)->getMessage());
        self::assertSame('No room "'.str_repeat('é', 64).'".', DeskRefused::unknownRoom($long)->getMessage());
        self::assertSame('No game "'.str_repeat('é', 10).'".', DeskRefused::unknownGame(str_repeat('é', 10))->getMessage());
    }

    public function testAScreenCostsNothingByDefault(): void
    {
        self::assertSame(0, new Screen(ScreenKind::Cleared, ResourceReference::of('member', '3'), new DateTimeImmutable())->penalty);
    }

    private function dueGames(int $count): Desk
    {
        $kit = new Desk();

        for ($index = 0; $index < $count; ++$index) {
            $kit->desk->start('g-'.$index, 'cell', ResourceReference::of('team', 't'.$index));
        }

        $kit->clock->sleep(60);

        return $kit;
    }
}
