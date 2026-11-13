<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\PuzzleBuilder\Event\GuessThrottled;
use Tilttek\PuzzleBuilder\Event\HintRevealed;
use Tilttek\PuzzleBuilder\Event\PartialAnswerGiven;
use Tilttek\PuzzleBuilder\Event\PuzzleOpened;
use Tilttek\PuzzleBuilder\Event\PuzzleSolved;
use Tilttek\PuzzleBuilder\Event\ScenarioCompleted;
use Tilttek\PuzzleBuilder\Event\WrongAnswerGiven;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\GuessOutcome;
use Tilttek\PuzzleBuilder\GuessResult;
use Tilttek\PuzzleBuilder\HintResult;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\PuzzleStatus;
use Tilttek\PuzzleBuilder\Scenario;
use Tilttek\PuzzleBuilder\Tests\Unit\Fixture\Manor;
use Tilttek\PuzzleBuilder\Tests\Unit\Fixture\Recorder;
use Tilttek\PuzzleBuilder\View\PuzzleView;
use Tilttek\PuzzleBuilder\View\ScenarioView;

#[CoversClass(PuzzleEngine::class)]
#[CoversClass(GuessResult::class)]
#[CoversClass(HintResult::class)]
#[CoversClass(PuzzleView::class)]
#[CoversClass(ScenarioView::class)]
#[CoversClass(PuzzleRefused::class)]
#[CoversClass(GuessThrottled::class)]
#[CoversClass(HintRevealed::class)]
#[CoversClass(PartialAnswerGiven::class)]
#[CoversClass(PuzzleOpened::class)]
#[CoversClass(PuzzleSolved::class)]
#[CoversClass(ScenarioCompleted::class)]
#[CoversClass(WrongAnswerGiven::class)]
final class PuzzleEngineTest extends TestCase
{
    private Scenario $scenario;

    private MockClock $clock;

    private Recorder $events;

    private PuzzleEngine $engine;

    private ResourceReference $team;

    protected function setUp(): void
    {
        $this->scenario = Manor::scenario();
        $this->clock = new MockClock('2026-11-13 20:00:00 UTC');
        $this->events = new Recorder();
        $this->engine = new PuzzleEngine(new InMemoryGuessLimiter(3, 60, $this->clock), $this->events, $this->clock);
        $this->team = ResourceReference::of('team', '7');
    }

    public function testThePuzzlesStartOpenLockedOrForTheHost(): void
    {
        $progress = Progress::start();

        self::assertSame(PuzzleStatus::Open, $this->engine->status($this->scenario, $progress, 'clock'));
        self::assertSame(PuzzleStatus::Locked, $this->engine->status($this->scenario, $progress, 'letter'));
        self::assertSame(PuzzleStatus::Locked, $this->engine->status($this->scenario, $progress, 'vault'));
    }

    public function testAWrongGuessIsCountedAndDispatched(): void
    {
        $result = $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'noon');

        self::assertSame(GuessOutcome::Wrong, $result->outcome);
        self::assertFalse($result->solved());
        self::assertNull($result->message);
        self::assertNull($result->retryAfter);
        self::assertSame([], $result->opened);
        self::assertFalse($result->completed);
        self::assertSame(1, $result->progress->guesses('clock'));
        self::assertEquals([new WrongAnswerGiven('manor', $this->team, 'clock', $this->clock->now())], $this->events->events);
    }

    public function testAPartialGuessSaysToKeepGoing(): void
    {
        $result = $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'Twelve!');

        self::assertSame(GuessOutcome::Partial, $result->outcome);
        self::assertSame('Which twelve?', $result->message);
        self::assertSame(1, $result->progress->guesses('clock'));
        self::assertFalse($result->progress->isSolved('clock'));
        self::assertEquals([new PartialAnswerGiven('manor', $this->team, 'clock', $this->clock->now())], $this->events->events);
    }

    public function testSolvingOpensWhatItUnlocksAndTheFinaleEndsTheScenario(): void
    {
        $first = $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'MIDNIGHT');

        self::assertTrue($first->solved());
        self::assertSame([], $first->opened);
        self::assertFalse($first->completed);
        self::assertSame(PuzzleStatus::Solved, $this->engine->status($this->scenario, $first->progress, 'clock'));

        $this->clock->sleep(30);
        $second = $this->engine->guess($this->scenario, $first->progress, $this->team, 'painting', 'le chene');

        self::assertSame(['vault'], $second->opened);
        self::assertSame(PuzzleStatus::Open, $this->engine->status($this->scenario, $second->progress, 'vault'));

        $last = $this->engine->guess($this->scenario, $second->progress, $this->team, 'vault', '4521');

        self::assertTrue($last->completed);
        self::assertSame(['clock', 'painting', 'vault'], array_keys($last->progress->solved()));
        $now = $this->clock->now();
        self::assertEquals([
            new PuzzleSolved('manor', $this->team, 'clock', $now->modify('-30 seconds'), false),
            new PuzzleSolved('manor', $this->team, 'painting', $now, false),
            new PuzzleOpened('manor', $this->team, 'vault', $now, false),
            new PuzzleSolved('manor', $this->team, 'vault', $now, false),
            new ScenarioCompleted('manor', $this->team, $now),
        ], $this->events->events);
    }

    public function testASolveAfterTheFinaleDoesNotEndTheScenarioAgain(): void
    {
        $scenario = Scenario::fromArray(['id' => 's', 'finale' => 'a', 'puzzles' => [
            ['id' => 'a', 'title' => 'A', 'answers' => ['a']],
            ['id' => 'b', 'title' => 'B', 'answers' => ['b']],
        ]]);
        $done = $this->engine->guess($scenario, Progress::start(), $this->team, 'a', 'a');
        $after = $this->engine->guess($scenario, $done->progress, $this->team, 'b', 'b');

        self::assertTrue($done->completed);
        self::assertFalse($after->completed);
    }

    public function testALockedPuzzleRefusesGuesses(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "vault" is still locked.');

        $this->engine->guess($this->scenario, Progress::start(), $this->team, 'vault', '4521');
    }

    public function testASolvedPuzzleRefusesGuesses(): void
    {
        $solved = $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'midnight')->progress;

        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "clock" is solved already.');

        $this->engine->guess($this->scenario, $solved, $this->team, 'clock', 'midnight');
    }

    public function testAnUnknownPuzzleIsRefused(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The scenario holds no puzzle "door".');

        $this->engine->guess($this->scenario, Progress::start(), $this->team, 'door', 'x');
    }

    public function testATeamNamedByAnEmailAddressIsRefused(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('A team is named by a stable identifier, never an email address.');

        $this->engine->guess($this->scenario, Progress::start(), ResourceReference::of('member', 'ana@example.org'), 'clock', 'x');
    }

    public function testAnInvalidGuessChangesNothing(): void
    {
        try {
            $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', '!!!');
            self::fail('An empty guess is refused.');
        } catch (InvalidAnswer) {
            self::assertSame([], $this->events->events);
        }
    }

    public function testGuessesPastTheLimitAreNotChecked(): void
    {
        $progress = Progress::start();

        for ($guess = 0; $guess < 3; ++$guess) {
            $progress = $this->engine->guess($this->scenario, $progress, $this->team, 'clock', 'noon')->progress;
        }

        $throttled = $this->engine->guess($this->scenario, $progress, $this->team, 'clock', 'midnight');

        self::assertSame(GuessOutcome::Throttled, $throttled->outcome);
        self::assertSame($progress, $throttled->progress);
        self::assertEquals($this->clock->now()->modify('+60 seconds'), $throttled->retryAfter);
        self::assertEquals(new GuessThrottled('manor', $this->team, 'clock', $this->clock->now()->modify('+60 seconds')), $this->events->events[3]);

        $other = $this->engine->guess($this->scenario, $progress, ResourceReference::of('team', '8'), 'clock', 'midnight');
        self::assertTrue($other->solved());
        self::assertSame(GuessOutcome::Correct, $this->engine->guess($this->scenario, $progress, $this->team, 'painting', 'le chene')->outcome);

        $this->clock->sleep(60);
        self::assertTrue($this->engine->guess($this->scenario, $progress, $this->team, 'clock', 'midnight')->solved());
    }

    public function testTheLimiterKeyNamesTheScenarioTheTeamAndThePuzzle(): void
    {
        self::assertSame('manor|team:7|clock', PuzzleEngine::key($this->scenario, $this->team, 'clock'));
    }

    public function testHintsAreRevealedTierAfterTier(): void
    {
        $first = $this->engine->hint($this->scenario, Progress::start(), $this->team, 'clock');

        self::assertSame('Look at the hands', $first->hint->text);
        self::assertSame(1, $first->tier);
        self::assertSame(1, $first->left);
        $this->clock->sleep(10);
        $second = $this->engine->hint($this->scenario, $first->progress, $this->team, 'clock', true);

        self::assertSame('Both point up', $second->hint->text);
        self::assertSame(2, $second->tier);
        self::assertSame(0, $second->left);
        self::assertTrue($second->progress->hints()[1]->pushed);
        self::assertFalse($second->progress->hints()[0]->pushed);
        self::assertEquals([
            new HintRevealed('manor', $this->team, 'clock', $this->clock->now()->modify('-10 seconds'), 1, false),
            new HintRevealed('manor', $this->team, 'clock', $this->clock->now(), 2, true),
        ], $this->events->events);

        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "clock" has no hint left to reveal.');

        $this->engine->hint($this->scenario, $second->progress, $this->team, 'clock');
    }

    public function testALockedPuzzleRevealsNoHint(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "vault" is still locked.');

        $this->engine->hint($this->scenario, Progress::start(), $this->team, 'vault');
    }

    public function testTheHostOpensAPuzzleThatWaitsForTheHost(): void
    {
        $progress = $this->engine->open($this->scenario, Progress::start(), $this->team, 'letter');

        self::assertSame(PuzzleStatus::Open, $this->engine->status($this->scenario, $progress, 'letter'));
        self::assertEquals([new PuzzleOpened('manor', $this->team, 'letter', $this->clock->now(), true)], $this->events->events);
        self::assertTrue($this->engine->guess($this->scenario, $progress, $this->team, 'letter', 'rosebud')->solved());

        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "letter" is open already.');

        $this->engine->open($this->scenario, $progress, $this->team, 'letter');
    }

    public function testTheHostOpensNoPuzzleThatOpensByItself(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "vault" opens by its unlock, not by the host.');

        $this->engine->open($this->scenario, Progress::start(), $this->team, 'vault');
    }

    public function testTheHostOpensForNoTeamNamedByAnEmailAddress(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('A team is named by a stable identifier, never an email address.');

        $this->engine->open($this->scenario, Progress::start(), ResourceReference::of('team', 'a@b'), 'letter');
    }

    public function testTheHostSkipsAPuzzleForPlayersWhoAreStuck(): void
    {
        $progress = $this->engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'midnight')->progress;
        $skipped = $this->engine->skip($this->scenario, $progress, $this->team, 'painting');

        self::assertTrue($skipped->solved());
        self::assertSame(['vault'], $skipped->opened);
        self::assertTrue($skipped->progress->solved()['painting']->skipped);
        self::assertFalse($skipped->progress->solved()['clock']->skipped);
        self::assertEquals(new PuzzleSolved('manor', $this->team, 'painting', $this->clock->now(), true), $this->events->events[1]);
    }

    public function testTheViewShowsNoAnswerAndHidesLockedPuzzles(): void
    {
        $progress = $this->engine->hint($this->scenario, Progress::start(), $this->team, 'clock')->progress;
        $progress = $this->engine->guess($this->scenario, $progress, $this->team, 'clock', 'noon')->progress;
        $progress = $this->engine->skip($this->scenario, $progress, $this->team, 'painting')->progress;

        $view = $this->engine->view($this->scenario, $progress);

        self::assertSame([
            'scenario' => 'manor',
            'completed' => false,
            'puzzles' => [
                ['id' => 'clock', 'status' => 'open', 'title' => 'The clock', 'hints' => ['Look at the hands'], 'hints_left' => 1, 'guesses' => 1, 'solved_at' => null, 'skipped' => false],
                ['id' => 'painting', 'status' => 'solved', 'title' => 'The painting', 'hints' => [], 'hints_left' => 0, 'guesses' => 0, 'solved_at' => '2026-11-13T20:00:00.000000Z', 'skipped' => true],
                ['id' => 'letter', 'status' => 'locked', 'title' => null, 'hints' => [], 'hints_left' => 0, 'guesses' => 0, 'solved_at' => null, 'skipped' => false],
                ['id' => 'vault', 'status' => 'locked', 'title' => null, 'hints' => [], 'hints_left' => 0, 'guesses' => 0, 'solved_at' => null, 'skipped' => false],
            ],
        ], $view->toArray());
        $json = json_encode($view->toArray(), \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsStringIgnoringCase('midnight', $json);
        self::assertStringNotContainsString('4521', $json);
    }

    public function testTheViewOfAFinishedGameSaysSo(): void
    {
        $progress = Progress::start();

        foreach (['clock' => 'midnight', 'painting' => 'le chene', 'vault' => '4521'] as $puzzle => $answer) {
            $progress = $this->engine->guess($this->scenario, $progress, $this->team, $puzzle, $answer)->progress;
        }

        $view = $this->engine->view($this->scenario, $progress);
        self::assertTrue($view->completed);
        self::assertSame('manor', $view->scenario);
        self::assertSame(PuzzleStatus::Solved, $view->puzzles[3]->status);
        self::assertFalse($view->puzzles[3]->skipped);
        self::assertSame('The vault', $view->puzzles[3]->title);
    }

    public function testAnEngineWithoutDispatcherMovesAllTheSame(): void
    {
        $engine = new PuzzleEngine(new InMemoryGuessLimiter(1, 60, $this->clock), clock: $this->clock);
        $progress = $engine->open($this->scenario, Progress::start(), $this->team, 'letter');
        $progress = $engine->hint($this->scenario, $progress, $this->team, 'clock')->progress;
        $progress = $engine->guess($this->scenario, $progress, $this->team, 'clock', 'twelve')->progress;

        self::assertSame(GuessOutcome::Throttled, $engine->guess($this->scenario, $progress, $this->team, 'clock', 'noon')->outcome);
        self::assertSame(GuessOutcome::Wrong, $engine->guess($this->scenario, $progress, $this->team, 'letter', 'noon')->outcome);
        self::assertTrue($engine->guess($this->scenario, $progress, $this->team, 'painting', 'le chene')->solved());
        self::assertTrue($engine->skip($this->scenario, $progress, $this->team, 'painting')->solved());
    }

    public function testALockedPuzzleCannotBeSkipped(): void
    {
        $this->expectException(PuzzleRefused::class);

        $this->engine->skip($this->scenario, Progress::start(), $this->team, 'vault');
    }

    public function testAGameIsPlayedWithoutADispatcher(): void
    {
        $engine = new PuzzleEngine(new InMemoryGuessLimiter(10, 60, $this->clock), clock: $this->clock);
        $progress = $engine->guess($this->scenario, Progress::start(), $this->team, 'clock', 'Midnight')->progress;
        $opened = $engine->guess($this->scenario, $progress, $this->team, 'painting', 'Le Chêne');

        self::assertSame(['vault'], $opened->opened);
        self::assertFalse($opened->completed);

        $done = $engine->guess($this->scenario, $opened->progress, $this->team, 'vault', '4521');

        self::assertTrue($done->completed);
    }
}
