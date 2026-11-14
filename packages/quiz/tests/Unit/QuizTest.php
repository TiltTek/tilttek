<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\Answer\ChoiceAnswer;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\AnswerResult;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Contract\QuestionSource;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Question\Choice;
use Tilttek\Quiz\Question\NumericQuestion;
use Tilttek\Quiz\Question\Rules;
use Tilttek\Quiz\Question\SingleChoiceQuestion;
use Tilttek\Quiz\Quiz;
use Tilttek\Quiz\Scoring;
use Tilttek\Quiz\Store\InMemoryQuestionSource;
use Tilttek\Quiz\Tests\Unit\Fixture\Quizzes;

#[CoversClass(Quiz::class)]
#[CoversClass(AnswerResult::class)]
#[CoversClass(InMemoryQuestionSource::class)]
#[CoversClass(Rules::class)]
final class QuizTest extends TestCase
{
    public function testItScoresAnAnswerWithItsQuestionAndItsTimer(): void
    {
        $quiz = Quizzes::trivia(Scoring::speed());
        $shown = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $timer = $quiz->timer(1, $shown);

        self::assertSame(20, $timer->limit);
        $result = $quiz->score(1, new NumberAnswer(1975), $timer, $shown->modify('+5 seconds'));

        self::assertSame('year', $result->question);
        self::assertTrue($result->grade->isRight());
        self::assertSame(200, $result->points);
        self::assertSame(75, $result->bonus);
        self::assertSame(5000, $result->elapsed);
        self::assertFalse($result->timedOut);
        self::assertSame(275, $result->total());
    }

    public function testAnAnswerAfterTheTimeIsTimedOut(): void
    {
        $quiz = Quizzes::trivia();
        $shown = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $result = $quiz->score(0, ChoiceAnswer::of('b'), $quiz->timer(0, $shown), $shown->modify('+21 seconds'));

        self::assertTrue($result->timedOut);
        self::assertTrue($result->grade->isWrong());
        self::assertSame(0, $result->total());
        self::assertSame(21_000, $result->elapsed);
    }

    public function testAQuestionsOwnLimitComesBeforeTheQuizs(): void
    {
        $quiz = new Quiz('q', [
            new SingleChoiceQuestion('own', 'Q', [new Choice('a', 'A'), new Choice('b', 'B')], 'a', timeLimit: 10),
            new SingleChoiceQuestion('none', 'Q', [new Choice('a', 'A'), new Choice('b', 'B')], 'a'),
        ], Scoring::speed(), 30);

        self::assertSame(10, $quiz->limitOf($quiz->question(0)));
        self::assertSame(30, $quiz->limitOf($quiz->question(1)));
        self::assertSame(300, $quiz->maxScore());
        self::assertSame(200, new Quiz('q', $quiz->questions, Scoring::accuracy(), 30)->maxScore());
        self::assertNull(new Quiz('q', [$quiz->question(1)])->limitOf($quiz->question(1)));
        self::assertSame(2, $quiz->count());
    }

    public function testAQuizAsksOneToTwoHundredDistinctQuestions(): void
    {
        $question = Quizzes::trivia()->question(0);

        try {
            new Quiz('q', []);
            self::fail('A quiz without questions was accepted.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('The quiz q asks 1 to 200 questions; it asks 0.', $invalidQuiz->getMessage());
        }

        $many = array_map(static fn (int $n): SingleChoiceQuestion => new SingleChoiceQuestion('q'.$n, 'Q', [new Choice('a', 'A'), new Choice('b', 'B')], 'a'), range(1, 201));
        self::assertSame(200, new Quiz('q', \array_slice($many, 0, 200))->count());

        try {
            new Quiz('q', $many);
            self::fail('A quiz of 201 questions was accepted.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertSame('The quiz q asks 1 to 200 questions; it asks 201.', $invalidQuiz->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('Two questions of the quiz q are dice.');
        new Quiz('q', [$question, $question]);
    }

    public function testItsIdentifierAndTimeLimitAreChecked(): void
    {
        try {
            new Quiz('bad id', [Quizzes::trivia()->question(0)]);
            self::fail('A bad identifier was accepted.');
        } catch (InvalidQuiz $invalidQuiz) {
            self::assertStringStartsWith('The identifier of a quiz', $invalidQuiz->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The time limit of the quiz q is 5 to 600 seconds; 2 is not allowed.');
        new Quiz('q', [Quizzes::trivia()->question(0)], timeLimit: 2);
    }

    public function testAPositionOutsideTheQuizIsRefused(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The quiz trivia has no question at position 2.');
        Quizzes::trivia()->question(2);
    }

    public function testAQuizIsComposedFromABank(): void
    {
        $questions = Quizzes::trivia()->questions;
        $source = new InMemoryQuestionSource(['lore' => $questions]);
        $quiz = Quiz::fromSource('drawn', $source, 'lore', 1, Scoring::speed(), 15);

        self::assertSame('drawn', $quiz->id);
        self::assertSame([$questions[0]], $quiz->questions);
        self::assertSame(15, $quiz->timeLimit);
        self::assertEquals(Scoring::speed(), $quiz->scoring);
        self::assertCount(2, Quiz::fromSource('drawn', $source, 'lore', 5)->questions);
        self::assertSame([], $source->questions('none', 3));
        self::assertSame([], $source->questions('lore', -1));

        foreach ([0, 201] as $count) {
            try {
                Quiz::fromSource('drawn', $source, 'lore', $count);
                self::fail('A count out of bounds was accepted.');
            } catch (InvalidQuiz $exception) {
                self::assertSame(\sprintf('The quiz drawn asks 1 to 200 questions; %d asked.', $count), $exception->getMessage());
            }
        }

        $this->expectException(InvalidQuiz::class);
        Quiz::fromSource('drawn', $source, 'none', 3);
    }

    public function testAQuizFromASourceTakesNoMoreThanItsCount(): void
    {
        $questions = Quizzes::trivia()->questions;
        $greedy = new readonly class($questions) implements QuestionSource {
            /** @param list<Question> $questions */
            public function __construct(private array $questions)
            {
            }

            public function questions(string $bank, int $count): array
            {
                return $this->questions;
            }
        };

        self::assertCount(1, Quiz::fromSource('drawn', $greedy, 'any', 1)->questions);
    }

    public function testAQuizFromASourceAsksUpToTwoHundredQuestions(): void
    {
        $source = new readonly class implements QuestionSource {
            public function questions(string $bank, int $count): array
            {
                return array_map(static fn (int $n): Question => new NumericQuestion('n'.$n, 'How many?', $n), range(1, $count));
            }
        };

        self::assertCount(Quiz::MOST_QUESTIONS, Quiz::fromSource('drawn', $source, 'any', Quiz::MOST_QUESTIONS)->questions);
    }
}
