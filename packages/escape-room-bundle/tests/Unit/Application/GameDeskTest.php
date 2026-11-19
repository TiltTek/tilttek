<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Event\GameFinished;
use Tilttek\EscapeRoom\Event\HintRequested;
use Tilttek\EscapeRoom\Exception\GameRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\GameOutcome;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Exception\DeskRefused;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;
use Tilttek\EscapeRoomBundle\Application\StoredGame;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Desk;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\PuzzleStatus;
use Tilttek\PuzzleBuilder\View\PuzzleView;

#[CoversClass(GameDesk::class)]
#[CoversClass(StoredGame::class)]
#[CoversClass(Screen::class)]
#[CoversClass(ScreenKind::class)]
#[CoversClass(DeskRefused::class)]
#[CoversClass(ConfiguredRoomCatalog::class)]
final class GameDeskTest extends TestCase
{
    private Desk $kit;

    private ResourceReference $team;

    private ResourceReference $host;

    protected function setUp(): void
    {
        $this->kit = new Desk();
        $this->team = ResourceReference::of('team', 'owls');
        $this->host = ResourceReference::of('member', '3');
    }

    public function testAGameStartsAndIsReadBack(): void
    {
        $game = $this->kit->desk->start('g-1', 'crypt', $this->team, 'session-9');

        self::assertSame('g-1', $game->id);
        self::assertSame('crypt', $game->room->id);
        self::assertSame('session-9', $game->game->session);
        self::assertEquals($game, $this->kit->desk->game('g-1'));
        self::assertSame(['g-1:start'], $this->kit->publisher->moves);
        self::assertSame(3_600, $this->kit->desk->timer($game)->remaining);
        self::assertSame(['g-1'], $this->kit->games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 10));
        self::assertSame([], $this->kit->games->due(new DateTimeImmutable('2026-11-14 20:59:59 UTC'), 10));
    }

    public function testAGameIsNotStartedTwice(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);

        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('The game "g-1" is there already.');

        $this->kit->desk->start('g-1', 'cell', $this->team);
    }

    public function testAGameOfAnUnknownRoomIsRefused(): void
    {
        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('No room "vault".');

        $this->kit->desk->start('g-1', 'vault', $this->team);
    }

    #[DataProvider('identifiers')]
    public function testTheIdentifierOfAGameIsChecked(string $id, bool $taken): void
    {
        if ($taken) {
            self::assertSame($id, $this->kit->desk->start($id, 'cell', $this->team)->id);

            return;
        }

        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a game holds 1 to 64 characters among A-Z, a-z, 0-9, - and _, from a letter or a digit.');

        $this->kit->desk->start($id, 'cell', $this->team);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function identifiers(): iterable
    {
        yield 'a digit first' => ['0abc_D-9', true];
        yield 'one letter' => ['A', true];
        yield '64 characters' => [str_repeat('a', 64), true];
        yield 'empty' => ['', false];
        yield '65 characters' => [str_repeat('a', 65), false];
        yield 'a dash first' => ['-a', false];
        yield 'a slash' => ['a/b', false];
        yield 'a new line after' => ["a\n", false];
    }

    public function testAnUnknownGameIsRefused(): void
    {
        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('No game "g-404".');

        $this->kit->desk->game('g-404');
    }

    public function testAGameWhoseRoomLeftTheCatalogIsRefused(): void
    {
        $this->kit->games->save('g-1', ['room' => 'vault', 'team' => 'team:owls'], null);

        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('No room "vault".');

        $this->kit->desk->game('g-1');
    }

    public function testAGameWithoutRoomIsRefused(): void
    {
        $this->kit->games->save('g-1', ['team' => 'team:owls'], null);

        $this->expectException(DeskRefused::class);
        $this->expectExceptionMessageIsOrContains('No room "".');

        $this->kit->desk->game('g-1');
    }

    public function testTheHostDrivesTheHintScreen(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);
        $this->kit->clock->sleep(30);

        $hint = $this->kit->desk->pushHint('g-1', 'candle', $this->host);
        self::assertSame(1, $hint->result->tier);
        self::assertEquals(new Screen(ScreenKind::Hint, $this->host, $this->kit->clock->now(), 'candle', 1, 120), $this->kit->desk->screen('g-1'));
        self::assertCount(1, $this->kit->desk->game('g-1')->game->progress->hints());

        $this->kit->desk->pushHint('g-1', 'candle', $this->host);
        self::assertEquals(new Screen(ScreenKind::Hint, $this->host, $this->kit->clock->now(), 'candle', 2, 30), $this->kit->desk->screen('g-1'));

        $this->kit->desk->message('g-1', $this->host, 'Look behind the clock');
        self::assertEquals(new Screen(ScreenKind::Message, $this->host, $this->kit->clock->now(), message: 'Look behind the clock'), $this->kit->desk->screen('g-1'));

        $this->kit->desk->clearScreen('g-1', $this->host);
        self::assertEquals(new Screen(ScreenKind::Cleared, $this->host, $this->kit->clock->now()), $this->kit->desk->screen('g-1'));
        self::assertSame(['g-1:start', 'g-1:hint', 'g-1:hint', 'g-1:message', 'g-1:clear'], $this->kit->publisher->moves);
    }

    public function testAHintAskedByThePlayersIsNotShownNorTold(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);
        $hint = $this->kit->desk->requestHint('g-1', 'candle');

        self::assertSame(120, $hint->penalty);
        self::assertCount(1, $this->kit->desk->game('g-1')->game->progress->hints());
        self::assertNull($this->kit->desk->screen('g-1'));
        self::assertSame(['g-1:start'], $this->kit->publisher->moves);
        self::assertContains(HintRequested::class, $this->kit->events->classes());
    }

    public function testARefusedMessageIsNotShown(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);

        try {
            $this->kit->desk->message('g-1', $this->host, ' ');
            self::fail('A blank message is refused.');
        } catch (InvalidRoom) {
            self::assertNull($this->kit->desk->screen('g-1'));
            self::assertSame(['g-1:start'], $this->kit->publisher->moves);
        }
    }

    public function testAnEscapeIsRecordedAndTold(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team, 's-1');
        $this->kit->clock->sleep(100);
        self::assertFalse($this->kit->desk->guess('g-1', 'candle', 'wick')->escaped());
        $this->kit->desk->skip('g-1', 'crest', $this->host);
        $this->kit->clock->sleep(20);

        $guess = $this->kit->desk->guess('g-1', 'tomb', '1347');

        self::assertTrue($guess->escaped());
        $game = $this->kit->desk->game('g-1');
        self::assertSame(GameOutcome::Escaped, $game->game->outcome);
        self::assertNull(GameDesk::deadline($game));
        self::assertSame([], $this->kit->games->due(new DateTimeImmutable('2027-01-01 UTC'), 10));
        self::assertSame(['g-1:start', 'g-1:skip', 'g-1:finish'], $this->kit->publisher->moves);
        self::assertSame([], $this->kit->desk->leaderboard('crypt'));

        $results = $this->kit->results->ofTeam($this->team);
        self::assertSame(['g-1'], array_keys($results));
        self::assertSame(420, $results['g-1']->finalSeconds);
        self::assertSame('s-1', $results['g-1']->session);
        self::assertFalse($results['g-1']->public);

        $this->kit->desk->consent('g-1', true);
        self::assertTrue($this->kit->desk->game('g-1')->game->public);
        $standings = $this->kit->desk->leaderboard('crypt');
        self::assertCount(1, $standings);
        self::assertSame(420, $standings[0]->result->finalSeconds);
        self::assertSame(['g-1:start', 'g-1:skip', 'g-1:finish', 'g-1:consent'], $this->kit->publisher->moves);

        $this->kit->desk->consent('g-1', false);
        self::assertSame([], $this->kit->desk->leaderboard('crypt'));
    }

    public function testTheConsentOfAGameWithoutResultChangesTheGameOnly(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);

        self::assertTrue($this->kit->desk->consent('g-1', true)->game->public);
        self::assertSame([], $this->kit->results->ofTeam($this->team));
    }

    public function testAPauseStopsTheDeadline(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);
        $this->kit->clock->sleep(60);

        $paused = $this->kit->desk->pause('g-1', $this->host);

        self::assertTrue($paused->game->paused());
        self::assertNull(GameDesk::deadline($paused));
        self::assertSame([], $this->kit->games->due(new DateTimeImmutable('2027-01-01 UTC'), 10));

        $this->kit->clock->sleep(600);
        $resumed = $this->kit->desk->resume('g-1', $this->host);
        self::assertSame('2026-11-14 21:10:00', GameDesk::deadline($resumed)?->format('Y-m-d H:i:s'));
        self::assertSame(['g-1:start', 'g-1:pause', 'g-1:resume'], $this->kit->publisher->moves);
    }

    public function testAnAbandonIsTold(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);

        self::assertSame(GameOutcome::Abandoned, $this->kit->desk->abandon('g-1')->game->outcome);
        self::assertSame(['g-1:start', 'g-1:finish'], $this->kit->publisher->moves);
        self::assertSame([], $this->kit->results->ofTeam($this->team));
    }

    public function testAGameEndsOnceWhenItsTimeIsUp(): void
    {
        $this->kit->desk->start('g-1', 'cell', $this->team);
        self::assertFalse($this->kit->desk->expire('g-1'));
        $this->kit->clock->sleep(59);
        self::assertFalse($this->kit->desk->expire('g-1'));

        $this->kit->clock->sleep(1);
        self::assertTrue($this->kit->desk->expire('g-1'));
        self::assertFalse($this->kit->desk->expire('g-1'));
        self::assertSame(GameOutcome::TimeUp, $this->kit->desk->game('g-1')->game->outcome);
        self::assertSame(['g-1:start', 'g-1:finish'], $this->kit->publisher->moves);
        self::assertCount(1, array_filter($this->kit->events->events, static fn (object $event): bool => $event instanceof GameFinished));
    }

    public function testTheGamesDueEndTheEarliestFirst(): void
    {
        $this->kit->desk->start('late', 'cell', $this->team);
        $this->kit->clock->modify('-10 seconds');
        $this->kit->desk->start('early', 'cell', ResourceReference::of('team', 'cats'));
        $this->kit->desk->start('long', 'crypt', ResourceReference::of('team', 'bats'));
        $this->kit->clock->sleep(120);

        self::assertSame(['early'], $this->kit->desk->expireDue(1));
        self::assertSame(['late'], $this->kit->desk->expireDue());
        self::assertSame([], $this->kit->desk->expireDue(GameDesk::MAX_BATCH));
        self::assertFalse($this->kit->desk->game('long')->game->over());
    }

    public function testAGameDueWhoseTimeIsNoLongerUpIsLeft(): void
    {
        $this->kit->desk->start('g-1', 'cell', $this->team);
        $this->kit->games->save('g-1', $this->kit->desk->game('g-1')->game->toArray(), new DateTimeImmutable('2026-11-14 19:00 UTC'));

        self::assertSame([], $this->kit->desk->expireDue());
        self::assertFalse($this->kit->desk->game('g-1')->game->over());
    }

    #[DataProvider('batches')]
    public function testABatchOfExpiriesIsBounded(int $limit): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A batch of expiries holds 1 to 1000 games.');

        $this->kit->desk->expireDue($limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function batches(): iterable
    {
        yield 'none' => [0];
        yield 'over' => [1_001];
    }

    #[DataProvider('limits')]
    public function testALeaderboardIsBounded(int $limit): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A leaderboard shows 1 to 1000 places.');

        $this->kit->desk->leaderboard('crypt', $limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function limits(): iterable
    {
        yield 'none' => [0];
        yield 'over' => [1_001];
    }

    public function testTheLeaderboardShowsAsManyPlacesAsAsked(): void
    {
        foreach (['a', 'b', 'c'] as $index => $team) {
            $this->kit->desk->start('g-'.$team, 'cell', ResourceReference::of('team', $team));
            $this->kit->clock->sleep(10 + $index);
            $this->kit->desk->guess('g-'.$team, 'door', 'key');
            $this->kit->desk->consent('g-'.$team, true);
        }

        self::assertCount(2, $this->kit->desk->leaderboard('cell', 2));
        self::assertCount(3, $this->kit->desk->leaderboard('cell', 1_000));
        self::assertCount(1, $this->kit->desk->leaderboard('cell', 1));
    }

    public function testThePuzzlesAreSeenAsTheHostSeesThem(): void
    {
        $game = $this->kit->desk->start('g-1', 'crypt', $this->team);
        $this->kit->desk->pushHint('g-1', 'candle', $this->host);
        $puzzles = $this->kit->desk->puzzles($this->kit->desk->game('g-1'))->puzzles;

        self::assertSame(['candle', 'crest', 'tomb'], array_map(static fn (PuzzleView $puzzle): string => $puzzle->id, $puzzles));
        self::assertSame(PuzzleStatus::Open, $puzzles[0]->status);
        self::assertSame(['Look at the top'], $puzzles[0]->hints);
        self::assertSame(1, $puzzles[0]->left);
        self::assertSame(PuzzleStatus::Locked, $this->kit->desk->puzzles($game)->puzzles[1]->status);
    }

    public function testTheMovesOfAGameOverAreRefused(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);
        $this->kit->desk->abandon('g-1');

        $this->expectException(GameRefused::class);

        $this->kit->desk->pushHint('g-1', 'candle', $this->host);
    }

    public function testThePuzzleRulesHold(): void
    {
        $this->kit->desk->start('g-1', 'crypt', $this->team);

        $this->expectException(PuzzleRefused::class);

        $this->kit->desk->skip('g-1', 'tomb', $this->host);
    }

    public function testADeskWithoutPublisherMovesAllTheSame(): void
    {
        $kit = new Desk(publishing: false);
        $kit->desk->start('g-1', 'crypt', $this->team);
        $kit->desk->message('g-1', $this->host, 'Hello');
        $kit->desk->clearScreen('g-1', $this->host);
        $kit->desk->abandon('g-1');

        self::assertSame([], $kit->publisher->moves);
        self::assertTrue($kit->desk->game('g-1')->game->over());
    }
}
