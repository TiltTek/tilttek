<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit\Answer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Answer\AnswerNormalizer;
use Tilttek\PuzzleBuilder\Answer\Verdict;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\GuessOutcome;

#[CoversClass(AnswerKey::class)]
#[CoversClass(Verdict::class)]
#[CoversClass(InvalidScenario::class)]
final class AnswerKeyTest extends TestCase
{
    public function testEveryAcceptedAnswerSolves(): void
    {
        $key = new AnswerKey(['Midnight', 'Minuit', '12 AM']);

        self::assertSame(GuessOutcome::Correct, $key->check('MIDNIGHT')->outcome);
        self::assertSame(GuessOutcome::Correct, $key->check('minuit!')->outcome);
        self::assertSame(GuessOutcome::Correct, $key->check('12am')->outcome);
        self::assertNull($key->check('12am')->message);
    }

    public function testAPartialAnswerSaysToKeepGoing(): void
    {
        $key = new AnswerKey(['midnight'], ['twelve' => 'Which twelve?', '1200' => 'Day or night?']);

        $verdict = $key->check('Twelve.');
        self::assertSame(GuessOutcome::Partial, $verdict->outcome);
        self::assertSame('Which twelve?', $verdict->message);
        self::assertSame('Day or night?', $key->check('12:00')->message);
    }

    public function testAnyOtherGuessIsWrong(): void
    {
        $verdict = new AnswerKey(['midnight'], ['twelve' => 'Which twelve?'])->check('noon');

        self::assertSame(GuessOutcome::Wrong, $verdict->outcome);
        self::assertNull($verdict->message);
    }

    public function testTheNormalizerOfTheKeyReadsTheGuesses(): void
    {
        $key = new AnswerKey(['Le Chêne'], normalizer: new AnswerNormalizer(keepAccents: true));

        self::assertSame(GuessOutcome::Correct, $key->check('le chêne')->outcome);
        self::assertSame(GuessOutcome::Wrong, $key->check('le chene')->outcome);
    }

    public function testAnInvalidGuessIsRefusedNotWrong(): void
    {
        $this->expectException(InvalidAnswer::class);

        new AnswerKey(['midnight'])->check('?!');
    }

    public function testTenAnswersAndTwentyPartialsAreTaken(): void
    {
        $answers = array_map(static fn (int $index): string => 'answer'.$index, range(1, 10));
        $partials = array_combine(array_map(static fn (int $index): string => 'step'.$index, range(1, 20)), array_fill(0, 20, 'Keep going'));
        $key = new AnswerKey($answers, $partials);

        self::assertSame(GuessOutcome::Correct, $key->check('answer10')->outcome);
        self::assertSame(GuessOutcome::Partial, $key->check('step20')->outcome);
    }

    public function testAKeyWithoutAnswerIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle accepts 1 to 10 answers.');

        new AnswerKey([]);
    }

    public function testElevenAnswersAreRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle accepts 1 to 10 answers.');

        new AnswerKey(array_map(static fn (int $index): string => 'answer'.$index, range(1, 11)));
    }

    public function testTwentyOnePartialsAreRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A puzzle has 20 partial answers at most.');

        new AnswerKey(['a'], array_combine(array_map(static fn (int $index): string => 'step'.$index, range(1, 21)), array_fill(0, 21, 'Keep going')));
    }

    public function testAnAnswerEmptyOnceNormalizedIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('An answer of the key is refused: The answer holds nothing once normalized.');

        new AnswerKey(['...']);
    }

    public function testAPartialAnswerThatIsAcceptedIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The partial answer "Mid-night" is accepted or given twice.');

        new AnswerKey(['midnight'], ['Mid-night' => 'Close']);
    }

    public function testAPartialAnswerGivenTwiceIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The partial answer "TWELVE" is accepted or given twice.');

        new AnswerKey(['midnight'], ['twelve' => 'Which twelve?', 'TWELVE' => 'Which twelve?']);
    }

    public function testAPartialAnswerWithoutMessageIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The message of a partial answer holds 1 to 200 characters.');

        new AnswerKey(['midnight'], ['twelve' => '  ']);
    }

    public function testAMessageOfTheLongestLengthIsTaken(): void
    {
        $message = str_repeat('é', 200);

        self::assertSame($message, new AnswerKey(['midnight'], ['twelve' => $message])->check('twelve')->message);
    }

    public function testALongerMessageIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('The message of a partial answer holds 1 to 200 characters.');

        new AnswerKey(['midnight'], ['twelve' => str_repeat('é', 201)]);
    }
}
