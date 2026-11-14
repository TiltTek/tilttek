<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Exception\QuizRefused;
use Tilttek\Quiz\Live\LiveSession;
use Tilttek\Quiz\Live\LiveState;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Standing;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;

#[CoversClass(LiveSession::class)]
final class LiveSessionTest extends TestCase
{
    private Quiz $quiz;

    private DateTimeImmutable $now;

    private LiveSession $session;

    protected function setUp(): void
    {
        $this->quiz = Quizzes::trivia();
        $this->now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $this->session = LiveSession::open('s', $this->quiz, '012345');
    }

    public function testASessionOpensInTheLobby(): void
    {
        self::assertSame('s', $this->session->id);
        self::assertSame('trivia', $this->session->quiz);
        self::assertSame('012345', $this->session->pin);
        self::assertSame(LiveState::Lobby, $this->session->state());
        self::assertSame(-1, $this->session->position());
        self::assertNull($this->session->timer($this->quiz));
        self::assertSame(1, $this->session->version());
        self::assertSame([], $this->session->players());
    }

    public function testAPinIsSixDigits(): void
    {
        foreach (['12345', '1234567', 'abcdef', "123456\n"] as $pin) {
            try {
                LiveSession::open('s', $this->quiz, $pin);
                self::fail('A bad PIN was accepted.');
            } catch (InvalidQuiz $exception) {
                self::assertSame('The PIN of a live session is six digits.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidQuiz::class);
        LiveSession::open('a session', $this->quiz, '123456');
    }

    public function testPlayersJoinOnceAndAPseudonymIsTakenOnce(): void
    {
        self::assertTrue($this->session->join(Player::guest('g1', 'Pixel')));
        self::assertSame(2, $this->session->version());
        self::assertFalse($this->session->join(Player::guest('g1', 'Pixel')));
        self::assertSame(2, $this->session->version());
        self::assertTrue($this->session->join(Player::member('m1')));
        self::assertSame(['g1', 'm1'], array_map(strval(...), array_keys($this->session->players())));
        self::assertEquals([Player::guest('g1', 'Pixel')], $this->session->guests());

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('Another guest of the session plays under this pseudonym.');
        $this->session->join(Player::guest('g2', 'PIXEL'));
    }

    public function testASessionHoldsAThousandPlayers(): void
    {
        for ($player = 1; $player <= LiveSession::MOST_PLAYERS; ++$player) {
            $this->session->join(Player::member('m'.$player));
        }

        self::assertCount(1000, $this->session->players());
        self::assertFalse($this->session->join(Player::member('m1')));

        $this->expectException(QuizRefused::class);
        $this->expectExceptionMessageIsOrContains('The live session s holds 1000 players, the most it takes.');
        $this->session->join(Player::member('m1001'));
    }

    public function testQuestionsAreShownClosedAndReviewed(): void
    {
        $this->session->join(Player::member('m1'));
        $this->session->join(Player::member('m2'));

        $timer = $this->session->next($this->quiz, $this->now);

        self::assertSame(LiveState::Question, $this->session->state());
        self::assertSame(0, $this->session->position());
        self::assertEquals($this->now, $timer->shownAt);
        self::assertEquals($timer, $this->session->timer($this->quiz));

        $result = $this->session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $this->now->modify('+3 seconds'));
        self::assertSame(100, $result->total());
        self::assertSame(3000, $result->elapsed);

        $this->session->close($this->quiz, $this->now->modify('+8 seconds'));
        self::assertSame(LiveState::Review, $this->session->state());
        $timedOut = $this->session->resultsOf('m2')[0];
        self::assertTrue($timedOut->timedOut);
        self::assertSame(8000, $timedOut->elapsed);
        self::assertSame('dice', $timedOut->question);
        self::assertSame(100, $this->session->resultsOf('m1')[0]->total());

        $this->session->next($this->quiz, $this->now->modify('+10 seconds'));
        self::assertSame(1, $this->session->position());
        $this->session->answer($this->quiz, 'm1', new NumberAnswer(1974), $this->now->modify('+11 seconds'));
        $this->session->close($this->quiz, $this->now->modify('+12 seconds'));

        try {
            $this->session->next($this->quiz, $this->now->modify('+13 seconds'));
            self::fail('A question past the last was shown.');
        } catch (QuizRefused $quizRefused) {
            self::assertSame('The live session s has asked every question of its quiz.', $quizRefused->getMessage());
        }

        self::assertFalse($this->session->isPerfect($this->quiz, 'm1'));
        $this->session->finish();
        self::assertTrue($this->session->isPerfect($this->quiz, 'm1'));
        self::assertFalse($this->session->isPerfect($this->quiz, 'm2'));
        self::assertFalse($this->session->isPerfect($this->quiz, 'nobody'));
        self::assertSame(['m1', 'm2'], array_map(static fn (Standing $standing): string => $standing->player, $this->session->leaderboard()->standings));
    }

    public function testAPlayerWhoMissedAQuestionIsNotPerfect(): void
    {
        $this->session->next($this->quiz, $this->now);
        $this->session->close($this->quiz, $this->now);
        $this->session->join(Player::member('late'));
        $this->session->next($this->quiz, $this->now);
        $this->session->answer($this->quiz, 'late', new NumberAnswer(1974), $this->now);
        $this->session->close($this->quiz, $this->now);
        $this->session->finish();

        self::assertCount(1, $this->session->resultsOf('late'));
        self::assertFalse($this->session->isPerfect($this->quiz, 'late'));
    }

    public function testAnswersAreRefusedOutsideTheRules(): void
    {
        $this->session->join(Player::member('m1'));
        $this->expectRefusal('The live session s shows no question to answer.', fn (): mixed => $this->session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $this->now));
        $this->expectRefusal('The live session s shows no question to close.', fn () => $this->session->close($this->quiz, $this->now));
        $this->session->next($this->quiz, $this->now);
        $this->expectRefusal('The live session s shows its next question from the lobby or a review only.', fn (): mixed => $this->session->next($this->quiz, $this->now));
        $this->expectRefusal('The player m9 has not joined the live session s.', fn (): mixed => $this->session->answer($this->quiz, 'm9', ChoiceAnswer::of('b'), $this->now));
        $this->expectRefusal('The time of this question is up.', fn (): mixed => $this->session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $this->now->modify('+21 seconds')));
        $this->session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $this->now->modify('+20 seconds'));
        $this->expectRefusal('The player m1 has answered this question already.', fn (): mixed => $this->session->answer($this->quiz, 'm1', ChoiceAnswer::of('a'), $this->now));
        $this->expectRefusal('The live session s shows a question: close it before the end.', fn () => $this->session->finish());
        $this->expectRefusal('The live session s plays the quiz trivia, not other.', fn (): mixed => $this->session->next(new Quiz('other', $this->quiz->questions), $this->now));
        $this->expectRefusal('The live session s plays the quiz trivia, not other.', fn (): mixed => $this->session->answer(new Quiz('other', $this->quiz->questions), 'm1', ChoiceAnswer::of('b'), $this->now));
        $this->expectRefusal('The live session s plays the quiz trivia, not other.', fn () => $this->session->close(new Quiz('other', $this->quiz->questions), $this->now));
        $this->session->close($this->quiz, $this->now);
        $this->expectRefusal('The live session s is not over: a guest becomes a member once it is.', fn () => $this->session->claimGuest('g1', Player::member('m2')));
        $this->session->finish();
        $this->expectRefusal('The live session s is over already.', fn () => $this->session->finish());
        $this->expectRefusal('The live session s is over.', fn (): mixed => $this->session->join(Player::member('m2')));
    }

    public function testAGuestsPlaceGoesToTheMemberTheyBecame(): void
    {
        $this->session->join(Player::guest('g1', 'Pixel'));
        $this->session->join(Player::guest('g2', 'Elf'));
        $this->session->join(Player::member('m1'));
        $this->session->next($this->quiz, $this->now);
        $this->session->answer($this->quiz, 'g1', ChoiceAnswer::of('b'), $this->now);
        $this->session->close($this->quiz, $this->now);
        $this->session->finish();

        $version = $this->session->version();

        $this->expectRefusal('The live session s has no guest m1.', fn () => $this->session->claimGuest('m1', Player::member('m5')));
        $this->expectRefusal('The live session s has no guest g9.', fn () => $this->session->claimGuest('g9', Player::member('m5')));
        $this->expectRefusal("A guest's place goes to a member, never to another guest.", fn () => $this->session->claimGuest('g1', Player::guest('g3', 'Orc')));
        $this->expectRefusal('The member m1 played the live session s already.', fn () => $this->session->claimGuest('g1', Player::member('m1')));

        $this->session->claimGuest('g1', Player::member('m5'));

        self::assertSame($version + 1, $this->session->version());
        self::assertArrayNotHasKey('g1', $this->session->players());
        self::assertFalse($this->session->players()['m5']->isGuest());
        self::assertSame([], $this->session->resultsOf('g1'));
        self::assertSame(100, $this->session->resultsOf('m5')[0]->total());
        self::assertSame(100, $this->session->leaderboard()->standingOf('m5')?->score);
        self::assertEquals([Player::guest('g2', 'Elf')], $this->session->guests());
    }

    public function testAClaimedPseudonymIsFreeAgain(): void
    {
        $other = LiveSession::open('t', $this->quiz, '000001');
        $other->join(Player::guest('g1', 'Élan'));
        $other->finish();
        $other->claimGuest('g1', Player::member('m1'));

        $reflection = new ReflectionProperty(LiveSession::class, 'pseudonyms');
        self::assertSame([], $reflection->getValue($other));
        self::assertStringNotContainsString('lan', serialize($other));
    }

    private function expectRefusal(string $message, Closure $call): void
    {
        try {
            $call();
            self::fail(\sprintf('Expected the refusal "%s".', $message));
        } catch (QuizRefused $quizRefused) {
            self::assertSame($message, $quizRefused->getMessage());
        }
    }

    public function testAPseudonymIsTakenOnceWhateverTheCaseOfItsAccents(): void
    {
        $this->session->join(Player::guest('g1', 'Élan'));

        $this->expectRefusal('Another guest of the session plays under this pseudonym.', fn (): bool => $this->session->join(Player::guest('g2', 'élan')));
    }

    public function testEachChangeGivesTheNextVersion(): void
    {
        $now = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $session = LiveSession::open('v', $this->quiz, '123456');
        self::assertSame(1, $session->version());
        $session->join(Player::member('m1'));
        self::assertSame(2, $session->version());
        $session->next($this->quiz, $now);
        self::assertSame(3, $session->version());
        $session->answer($this->quiz, 'm1', ChoiceAnswer::of('b'), $now);
        self::assertSame(4, $session->version());
        $session->close($this->quiz, $now);
        self::assertSame(5, $session->version());
        $session->finish();
        self::assertSame(6, $session->version());

        $guests = LiveSession::open('w', $this->quiz, '123456');
        $guests->join(Player::guest('g1', 'Pixel'));
        $guests->finish();
        $guests->claimGuest('g1', Player::member('m9'));
        self::assertSame(4, $guests->version());
    }
}
