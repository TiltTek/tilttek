<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Contract\PlayStore;
use Tilttek\Quiz\Event\PerfectScore;
use Tilttek\Quiz\Event\QuizCompleted;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizConflict;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Scoring;
use Tilttek\Quiz\SelfPaced\Feedback;
use Tilttek\Quiz\SelfPaced\PerfectScoreRule;
use Tilttek\Quiz\SelfPaced\Play;
use Tilttek\Quiz\SelfPaced\Replays;
use Tilttek\Quiz\SelfPacedQuizzes;
use Tilttek\Quiz\Store\InMemoryPlayStore;
use Tilttek\Quiz\Store\InMemoryQuizCatalog;
use Tilttek\Quiz\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;
use Tilttek\Quiz\Tests\Unit\Fixture\RacingPlayStore;
use Tilttek\Quiz\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Quiz\View\ResultView;

#[CoversClass(SelfPacedQuizzes::class)]
#[CoversClass(Play::class)]
#[CoversClass(InMemoryPlayStore::class)]
#[CoversClass(InMemoryQuizCatalog::class)]
#[CoversClass(QuizCompleted::class)]
#[CoversClass(PerfectScore::class)]
#[CoversClass(QuizRefused::class)]
#[CoversClass(QuizConflict::class)]
#[CoversClass(Replays::class)]
#[CoversClass(Feedback::class)]
#[CoversClass(PerfectScoreRule::class)]
final class SelfPacedQuizzesTest extends TestCase
{
    private FrozenClock $clock;

    private RecordingDispatcher $events;

    private InMemoryPlayStore $plays;

    private SelfPacedQuizzes $quizzes;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock();
        $this->events = new RecordingDispatcher();
        $this->plays = new InMemoryPlayStore();
        $this->quizzes = $this->quizzes();
    }

    public function testAMemberPlaysAtTheirOwnPaceAndAPerfectRunIsSaid(): void
    {
        $play = $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));

        self::assertSame('trivia', $play->quiz);
        self::assertSame(0, $play->position());
        self::assertSame(1, $play->attempt);
        self::assertFalse($play->isPractice());
        self::assertEquals($this->clock->now, $play->startedAt);
        self::assertSame(1, $play->version());
        self::assertEquals($this->clock->now, $play->timer(Quizzes::trivia())->shownAt);

        $this->clock->move('+10 seconds');
        self::assertNull($this->quizzes->answer('play-1', 'member:44', ChoiceAnswer::of('b')));
        self::assertSame([], $this->events->events);

        $stored = $this->quizzes->play('play-1', 'member:44');
        self::assertSame(1, $stored->position());
        self::assertSame(2, $stored->version());
        self::assertFalse($stored->isFinished());
        self::assertFalse($stored->isPerfect());
        self::assertNull($stored->finishedAt());
        self::assertEquals($this->clock->now, $stored->timer(Quizzes::trivia())->shownAt);

        $this->clock->move('+4 seconds');
        $last = $this->quizzes->answer('play-1', 'member:44', new NumberAnswer(1974));
        self::assertNotNull($last);
        self::assertSame(280, $last->total());
        $done = $this->quizzes->play('play-1', 'member:44');

        self::assertTrue($done->isFinished());
        self::assertTrue($done->isPerfect());
        self::assertEquals($this->clock->now, $done->finishedAt());
        self::assertSame(125 + 200 + 80, $done->score());
        self::assertCount(2, $this->quizzes->results('play-1', 'member:44'));
        self::assertSame(125, $this->quizzes->results('play-1', 'member:44')[0]->total());
        self::assertEquals(new ResultView('trivia', 'play-1', 'member:44', 405, 450, true, true), $this->quizzes->result('play-1', 'member:44'));
        self::assertEquals([
            new QuizCompleted('trivia', 'play-1', 'member:44', true),
            new PerfectScore('trivia', 'play-1', 'member:44'),
        ], $this->events->events);
    }

    public function testARunWithAWrongAnswerIsCompletedWithoutPerfection(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->quizzes->answer('play-1', 'member:44', ChoiceAnswer::of('a'));
        $this->quizzes->answer('play-1', 'member:44', new NumberAnswer(1974));

        self::assertFalse($this->quizzes->play('play-1', 'member:44')->isPerfect());
        self::assertEquals([new QuizCompleted('trivia', 'play-1', 'member:44', false)], $this->events->events);
    }

    public function testNothingOfAPlayThatIsNotOverTellsARightAnswer(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        self::assertNull($this->quizzes->answer('play-1', 'member:44', ChoiceAnswer::of('b')));

        try {
            $this->quizzes->results('play-1', 'member:44');
            self::fail('The results of a play that is not over were shown.');
        } catch (QuizRefused $quizRefused) {
            self::assertSame('The results of the play play-1 are shown once it is over.', $quizRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The results of the play play-1 are shown once it is over.');
        $this->quizzes->result('play-1', 'member:44');
    }

    public function testTheResultsMayBeShownAfterEachAnswer(): void
    {
        $quizzes = $this->quizzes(feedback: Feedback::AfterEachAnswer);
        $quizzes->start('play-1', 'trivia', Player::member('member:44'));

        $this->clock->move('+25 seconds');

        $late = $quizzes->answer('play-1', 'member:44', ChoiceAnswer::of('b'));

        self::assertNotNull($late);
        self::assertTrue($late->timedOut);
        self::assertSame(1, $quizzes->play('play-1', 'member:44')->position());
        self::assertEquals([$late], $quizzes->results('play-1', 'member:44'));

        $view = $quizzes->result('play-1', 'member:44');
        self::assertFalse($view->completed);
        self::assertSame(0, $view->score);
    }

    public function testAFinishedPlayTakesNoMoreAnswers(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->quizzes->answer('play-1', 'member:44', ChoiceAnswer::of('b'));
        $this->quizzes->answer('play-1', 'member:44', new NumberAnswer(1974));

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The play play-1 is over.');
        $this->quizzes->answer('play-1', 'member:44', new NumberAnswer(1974));
    }

    public function testAPlayBelongsToItsPlayer(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));

        foreach ([
            fn (): mixed => $this->quizzes->answer('play-1', 'member:45', ChoiceAnswer::of('b')),
            fn (): mixed => $this->quizzes->play('play-1', 'member:45'),
            fn (): mixed => $this->quizzes->results('play-1', 'member:45'),
            fn (): mixed => $this->quizzes->result('play-1', 'member:45'),
        ] as $move) {
            try {
                $move();
                self::fail("Another player's play was reached.");
            } catch (QuizRefused $quizRefused) {
                self::assertSame('No play has the identifier play-1.', $quizRefused->getMessage());
            }
        }

        self::assertSame(0, $this->quizzes->play('play-1', 'member:44')->position());

        $play = Play::start('p', Quizzes::trivia(), Player::member('m'), $this->clock->now());
        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The play p belongs to another player.');
        $play->answer(Quizzes::trivia(), 'n', ChoiceAnswer::of('b'), $this->clock->now());
    }

    public function testAPlayAfterTheFirstIsForPracticeAndNeverPerfectForTheEvents(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->playPerfectly('play-1');
        $replay = $this->quizzes->start('play-2', 'trivia', Player::member('member:44'));

        self::assertSame(2, $replay->attempt);
        self::assertTrue($replay->isPractice());

        $this->playPerfectly('play-2');

        self::assertEquals([
            new QuizCompleted('trivia', 'play-1', 'member:44', true),
            new PerfectScore('trivia', 'play-1', 'member:44'),
            new QuizCompleted('trivia', 'play-2', 'member:44', true, true),
        ], $this->events->events);
        self::assertTrue($this->quizzes->result('play-2', 'member:44')->practice);
        self::assertFalse($this->quizzes->result('play-1', 'member:44')->practice);
    }

    public function testAPlayLeftUnfinishedStillMakesTheNextOnePractice(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->quizzes->start('play-x', 'trivia', Player::member('member:45'));

        $replay = $this->quizzes->start('play-2', 'trivia', Player::member('member:44'));
        $third = $this->quizzes->start('play-3', 'trivia', Player::member('member:44'));

        self::assertSame(2, $replay->attempt);
        self::assertSame(3, $third->attempt);

        $this->playPerfectly('play-2');
        self::assertEquals([new QuizCompleted('trivia', 'play-2', 'member:44', true, true)], $this->events->events);
    }

    public function testEveryPerfectPlayMayBeSaidWithItsPractice(): void
    {
        $this->quizzes = $this->quizzes(perfectScore: PerfectScoreRule::EveryPerfectPlay);
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->playPerfectly('play-1');
        $this->quizzes->start('play-2', 'trivia', Player::member('member:44'));
        $this->playPerfectly('play-2');

        self::assertEquals([
            new QuizCompleted('trivia', 'play-1', 'member:44', true),
            new PerfectScore('trivia', 'play-1', 'member:44'),
            new QuizCompleted('trivia', 'play-2', 'member:44', true, true),
            new PerfectScore('trivia', 'play-2', 'member:44', true),
        ], $this->events->events);
    }

    public function testReplaysMayBeRefused(): void
    {
        $quizzes = $this->quizzes(replays: Replays::Refused);
        $quizzes->start('play-1', 'trivia', Player::member('member:44'));
        self::assertSame(1, $quizzes->start('play-x', 'trivia', Player::member('member:45'))->attempt);

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The player member:44 played the quiz trivia already.');
        $quizzes->start('play-2', 'trivia', Player::member('member:44'));
    }

    public function testTwoPlaysStartedAtOnceNeverBothCount(): void
    {
        $store = new RacingPlayStore(new InMemoryPlayStore(), SelfPacedQuizzes::ATTEMPTS - 1);
        $play = $this->quizzes($store)->start('play-1', 'trivia', Player::member('member:44'));

        self::assertSame(SelfPacedQuizzes::ATTEMPTS, $play->attempt);
        self::assertSame(SelfPacedQuizzes::ATTEMPTS, $store->counts);
        self::assertSame(SelfPacedQuizzes::ATTEMPTS, $store->inner->countOf('trivia', 'member:44'));
    }

    public function testAStartThatAlwaysMeetsAnotherIsLetThrough(): void
    {
        $store = new RacingPlayStore(new InMemoryPlayStore(), SelfPacedQuizzes::ATTEMPTS);

        try {
            $this->quizzes($store)->start('play-1', 'trivia', Player::member('member:44'));
            self::fail('A play was started at an attempt taken.');
        } catch (QuizConflict $quizConflict) {
            self::assertSame('The player member:44 has a play of the quiz trivia at attempt 4 already.', $quizConflict->getMessage());
        }

        self::assertSame(SelfPacedQuizzes::ATTEMPTS, $store->counts);
        self::assertNull($store->find('play-1'));
    }

    public function testATakenIdentifierIsLetThroughAtOnce(): void
    {
        $store = new RacingPlayStore(new InMemoryPlayStore(), 0);
        $quizzes = $this->quizzes($store);
        $quizzes->start('play-1', 'trivia', Player::member('member:44'));

        try {
            $quizzes->start('play-1', 'trivia', Player::member('member:45'));
            self::fail('Two plays had the same identifier.');
        } catch (QuizConflict $quizConflict) {
            self::assertSame('The play play-1 is at version 1, not 0.', $quizConflict->getMessage());
        }

        self::assertSame(2, $store->counts);
    }

    public function testAPlayerIsErasedWithTheirPlays(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->quizzes->start('play-2', 'trivia', Player::member('member:44'));
        $this->quizzes->start('play-x', 'trivia', Player::member('member:45'));

        self::assertSame(2, $this->quizzes->erasePlayer('member:44'));
        self::assertSame(0, $this->quizzes->erasePlayer('member:44'));
        self::assertNull($this->plays->find('play-1'));
        self::assertNull($this->plays->find('play-2'));
        self::assertNotNull($this->plays->find('play-x'));

        $this->expectException(InvalidQuiz::class);
        $this->quizzes->erasePlayer('a member');
    }

    public function testAGuestNeverPlaysAtTheirOwnPace(): void
    {
        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('A guest plays in a live session only');
        $this->quizzes->start('play-1', 'trivia', Player::guest('guest-1', 'Pixel'));
    }

    public function testUnknownQuizzesAndPlaysAreRefused(): void
    {
        try {
            $this->quizzes->start('play-1', 'nope', Player::member('member:44'));
            self::fail('An unknown quiz was played.');
        } catch (QuizRefused $quizRefused) {
            self::assertSame('No quiz has the identifier nope.', $quizRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('No play has the identifier nope.');
        $this->quizzes->answer('nope', 'member:44', ChoiceAnswer::of('b'));
    }

    public function testAPlayOfAnotherQuizIsRefused(): void
    {
        $play = Play::start('p', Quizzes::trivia(), Player::member('m'), $this->clock->now());
        $other = new Quiz('other', Quizzes::trivia()->questions);

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The play p is a run through the quiz trivia, not other.');
        $play->answer($other, 'm', ChoiceAnswer::of('b'), $this->clock->now());
    }

    public function testAPlayIdentifierAndAttemptAreChecked(): void
    {
        self::assertSame(1, Play::start('p', Quizzes::trivia(), Player::member('m'), $this->clock->now(), 1)->attempt);

        try {
            Play::start('p', Quizzes::trivia(), Player::member('m'), $this->clock->now(), 0);
            self::fail('A play was started at attempt 0.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('An attempt is counted from 1; 0 is not one.', $invalidQuiz->getMessage());
        }

        $this->expectException(InvalidQuiz::class);
        Play::start('a play', Quizzes::trivia(), Player::member('m'), $this->clock->now());
    }

    public function testTheStoreKeepsVersions(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));

        $first = $this->plays->find('play-1');
        $second = $this->plays->find('play-1');
        self::assertNotNull($first);
        self::assertNotNull($second);
        $first->answer(Quizzes::trivia(), 'member:44', ChoiceAnswer::of('b'), $this->clock->now());
        $second->answer(Quizzes::trivia(), 'member:44', ChoiceAnswer::of('a'), $this->clock->now());
        $this->plays->save($first);
        self::assertSame(1, $this->plays->find('play-1')?->position());

        try {
            $this->plays->save($second);
            self::fail('A stale play was saved.');
        } catch (QuizConflict $quizConflict) {
            self::assertSame('The play play-1 is at version 2, not 1.', $quizConflict->getMessage());
        }

        $loose = Play::start('loose', Quizzes::trivia(), Player::member('m'), $this->clock->now());
        $loose->answer(Quizzes::trivia(), 'm', ChoiceAnswer::of('b'), $this->clock->now());
        $this->expectExceptionMessageIsOrContains('The play loose is not stored.');
        $this->plays->save($loose);
    }

    public function testTheStoreKeepsOnePlayPerAttempt(): void
    {
        $this->plays->add(Play::start('a', Quizzes::trivia(), Player::member('m'), $this->clock->now()));
        $this->plays->add(Play::start('b', Quizzes::trivia(), Player::member('n'), $this->clock->now()));
        $this->plays->add(Play::start('c', new Quiz('other', Quizzes::trivia()->questions), Player::member('m'), $this->clock->now()));
        $this->plays->add(Play::start('d', Quizzes::trivia(), Player::member('m'), $this->clock->now(), 2));

        self::assertSame(2, $this->plays->countOf('trivia', 'm'));
        self::assertSame(1, $this->plays->countOf('trivia', 'n'));
        self::assertSame(1, $this->plays->countOf('other', 'm'));
        self::assertSame(0, $this->plays->countOf('other', 'n'));

        $this->expectException(QuizConflict::class);
        $this->expectExceptionMessageIsOrContains('The player m has a play of the quiz trivia at attempt 2 already.');
        $this->plays->add(Play::start('e', Quizzes::trivia(), Player::member('m'), $this->clock->now(), 2));
    }

    public function testAPlayChangedWithoutSavingLeavesTheStoredOne(): void
    {
        $this->quizzes->start('play-1', 'trivia', Player::member('member:44'));
        $this->plays->find('play-1')?->answer(Quizzes::trivia(), 'member:44', ChoiceAnswer::of('b'), $this->clock->now());

        self::assertSame(0, $this->plays->find('play-1')?->position());
        self::assertNull($this->plays->find('nope'));
    }

    private function quizzes(
        ?PlayStore $plays = null,
        Replays $replays = Replays::Practice,
        Feedback $feedback = Feedback::AfterPlay,
        PerfectScoreRule $perfectScore = PerfectScoreRule::OncePerQuizAndMember,
    ): SelfPacedQuizzes {
        return new SelfPacedQuizzes(new InMemoryQuizCatalog(Quizzes::trivia(Scoring::speed())), $plays ?? $this->plays, $this->events, $this->clock, $replays, $feedback, $perfectScore);
    }

    private function playPerfectly(string $play): void
    {
        $this->quizzes->answer($play, 'member:44', ChoiceAnswer::of('b'));
        $this->quizzes->answer($play, 'member:44', new NumberAnswer(1974));
    }
}
