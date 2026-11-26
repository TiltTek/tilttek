<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Predictions\Exception\InvalidPrediction;
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\QuestionStatus;
use Tilttek\Predictions\Scoring;

#[CoversClass(Question::class)]
#[CoversClass(InvalidPrediction::class)]
final class QuestionTest extends TestCase
{
    public function testAQuestionTakesPredictionsUntilItsDeadline(): void
    {
        $question = $this->question();

        self::assertSame('2026-12-05 19:00:00 UTC', $question->closesAt->format('Y-m-d H:i:s e'));
        self::assertNull($question->closedAt);
        self::assertNull($question->outcome);
        self::assertTrue($question->takesPredictionsAt(new DateTimeImmutable('2026-12-05 18:59:59 UTC')));
        self::assertFalse($question->takesPredictionsAt(new DateTimeImmutable('2026-12-05 19:00:00 UTC')));
        self::assertFalse($question->resolved('home', new DateTimeImmutable('2026-12-01 00:00:00 UTC'))->takesPredictionsAt(new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        self::assertFalse($question->cancelled(new DateTimeImmutable('2026-12-01 00:00:00 UTC'))->takesPredictionsAt(new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
    }

    public function testResolvingAndCancelling(): void
    {
        $question = self::question(Scoring::Probability);

        $resolved = $question->resolved('away', new DateTimeImmutable('2026-12-05 16:00:00-05:00'));
        self::assertSame([QuestionStatus::Resolved, 'away', '2026-12-05 21:00:00 UTC'], [$resolved->status, $resolved->outcome, $resolved->closedAt?->format('Y-m-d H:i:s e')]);
        self::assertSame([['home', 'draw', 'away'], 30, Scoring::Probability], [$resolved->outcomes, $resolved->points, $resolved->scoring]);
        self::assertSame('2026-12-05 19:00:00', $resolved->closesAt->format('Y-m-d H:i:s'));
        self::assertTrue($resolved->sameAs($question));

        $cancelled = $question->cancelled(new DateTimeImmutable('2026-12-05 21:00:00 UTC'));
        self::assertSame([QuestionStatus::Cancelled, null], [$cancelled->status, $cancelled->outcome]);
        self::assertSame([['home', 'draw', 'away'], 30, Scoring::Probability], [$cancelled->outcomes, $cancelled->points, $cancelled->scoring]);
        self::assertTrue($cancelled->key->equals($question->key));
    }

    public function testThePointsOfAPick(): void
    {
        $choice = $this->question();
        self::assertSame(0, $choice->pointsFor(Pick::choice('home')));
        self::assertSame(30, $choice->resolved('home', new DateTimeImmutable())->pointsFor(Pick::choice('home')));
        self::assertSame(0, $choice->resolved('home', new DateTimeImmutable())->pointsFor(Pick::choice('away')));

        $forecast = self::question(Scoring::Probability);
        $pick = Pick::probabilities(['home' => 60, 'draw' => 30, 'away' => 10])->for($forecast);
        self::assertSame(0, $forecast->pointsFor($pick));
        // 30 × (1 - 0.26 / 2) = 26.1.
        self::assertSame(26, $forecast->resolved('home', new DateTimeImmutable())->pointsFor($pick));
    }

    public function testSameAsComparesTheDefinition(): void
    {
        $question = $this->question();

        self::assertTrue($question->sameAs(new Question(QuestionKey::fromString('final:2026'), ['home', 'draw', 'away'], new DateTimeImmutable('2026-12-05 14:00:00-05:00'), 30)));
        self::assertFalse($question->sameAs(new Question(QuestionKey::fromString('final:2027'), ['home', 'draw', 'away'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 30)));
    }

    public function testTheBounds(): void
    {
        $outcomes = array_map(static fn (int $index): string => 'o'.$index, range(1, 32));

        self::assertCount(32, new Question(QuestionKey::fromString('k'), $outcomes, new DateTimeImmutable(), 1_000_000)->outcomes);
        self::assertSame(1, new Question(QuestionKey::fromString('k'), ['a', 'b'], new DateTimeImmutable(), 1)->points);
        self::assertSame('9-z_.', Question::checkOutcome('9-z_.'));
        self::assertSame(str_repeat('a', 64), Question::checkOutcome(str_repeat('a', 64)));
    }

    public function testInvalidQuestions(): void
    {
        $key = QuestionKey::fromString('final:2026');
        $at = new DateTimeImmutable('2026-12-05 19:00:00 UTC');
        $cases = [
            'A question has 2 to 32 outcomes; this one has 1.' => static fn (): Question => new Question($key, ['home'], $at, 30),
            'A question has 2 to 32 outcomes; this one has 33.' => static fn (): Question => new Question($key, array_map(static fn (int $index): string => 'o'.$index, range(1, 33)), $at, 30),
            'An outcome is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "member:12" is not.' => static fn (): Question => new Question($key, ['member:12', 'home'], $at, 30),
            'A question names each outcome once.' => static fn (): Question => new Question($key, ['home', 'home'], $at, 30),
            'A question gives 1 to 1000000 points; 0 is out of bounds.' => static fn (): Question => new Question($key, ['home', 'away'], $at, 0),
            'A question gives 1 to 1000000 points; 1000001 is out of bounds.' => static fn (): Question => new Question($key, ['home', 'away'], $at, 1_000_001),
            'A question is closed at a moment exactly when it is resolved or cancelled.' => static fn (): Question => new Question($key, ['home', 'away'], $at, 30, closedAt: $at),
            'A question has an outcome exactly when it is resolved.' => static fn (): Question => new Question($key, ['home', 'away'], $at, 30, status: QuestionStatus::Cancelled, outcome: 'home', closedAt: $at),
            '"draw" is not an outcome of the question "final:2026".' => static fn (): Question => new Question($key, ['home', 'away'], $at, 30, status: QuestionStatus::Resolved, outcome: 'draw', closedAt: $at),
        ];

        foreach ($cases as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidPrediction $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        foreach (['', '-a', 'A', str_repeat('a', 65), "a\n"] as $outcome) {
            try {
                Question::checkOutcome($outcome);
                self::fail($outcome);
            } catch (InvalidPrediction) {
            }
        }

        try {
            new Question($key, ['home', 'away'], $at, 30, status: QuestionStatus::Resolved, closedAt: $at);
            self::fail('A resolved question without an outcome.');
        } catch (InvalidPrediction $invalidPrediction) {
            self::assertSame('A question has an outcome exactly when it is resolved.', $invalidPrediction->getMessage());
        }

        try {
            new Question($key, ['home', 'away'], $at, 30, status: QuestionStatus::Resolved, outcome: 'home');
            self::fail('A resolved question without a moment.');
        } catch (InvalidPrediction $invalidPrediction) {
            self::assertSame('A question is closed at a moment exactly when it is resolved or cancelled.', $invalidPrediction->getMessage());
        }
    }

    private function question(Scoring $scoring = Scoring::Choice): Question
    {
        return new Question(QuestionKey::fromString('final:2026'), ['home', 'draw', 'away'], new DateTimeImmutable('2026-12-05 14:00:00-05:00'), 30, $scoring);
    }
}
