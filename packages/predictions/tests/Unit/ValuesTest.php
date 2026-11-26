<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Predictions\Exception\InvalidPrediction;
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Prediction;
use Tilttek\Predictions\PredictionId;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\Scoring;

#[CoversClass(QuestionKey::class)]
#[CoversClass(PredictionId::class)]
#[CoversClass(Pick::class)]
#[CoversClass(Prediction::class)]
final class ValuesTest extends TestCase
{
    public function testAQuestionKey(): void
    {
        self::assertSame('final:2026', QuestionKey::fromString('final:2026')->value);
        self::assertSame(200, \strlen(QuestionKey::fromString(str_repeat('k', 200))->value));
        self::assertTrue(QuestionKey::fromString('a')->equals(QuestionKey::fromString('a')));
        self::assertFalse(QuestionKey::fromString('a')->equals(QuestionKey::fromString('b')));
        self::assertSame('a#', QuestionKey::fromString('a#')->value);

        $cases = [
            'A question key is 1 to 200 bytes; this one is 0.' => '',
            'A question key is 1 to 200 bytes; this one is 201.' => str_repeat('k', 201),
            'A question key cannot contain whitespace or control characters.' => "a\tb",
            '"#final" starts with "#", which only the keys the ledger derives start with.' => '#final',
        ];

        foreach ($cases as $message => $key) {
            try {
                QuestionKey::fromString($key);
                self::fail($message);
            } catch (InvalidPrediction $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        foreach (["a\x7F", "a\x00", 'a b'] as $key) {
            try {
                QuestionKey::fromString($key);
                self::fail(bin2hex($key));
            } catch (InvalidPrediction $invalid) {
                self::assertSame('A question key cannot contain whitespace or control characters.', $invalid->getMessage());
            }
        }
    }

    public function testAPredictionIdentifier(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(3));
        $id = PredictionId::generate($randomizer);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id->value);
        self::assertFalse($id->equals(PredictionId::generate($randomizer)));
        self::assertTrue($id->equals(PredictionId::fromString($id->value)));

        foreach ([str_repeat('a', 31), str_repeat('a', 33), str_repeat('A', 32), str_repeat('a', 32)."\n", 'x'.str_repeat('a', 32)] as $text) {
            try {
                PredictionId::fromString($text);
                self::fail($text);
            } catch (InvalidPrediction $invalid) {
                self::assertSame('A prediction identifier is 32 lower-case hexadecimal digits.', $invalid->getMessage());
            }
        }
    }

    public function testAChoice(): void
    {
        $question = self::question(Scoring::Choice);
        $pick = Pick::choice('home');

        self::assertSame('home', $pick->choice);
        self::assertSame([], $pick->percents);
        self::assertSame($pick, $pick->for($question));
        $this->assertInvalid('The question "q" takes a probability for each outcome, not one outcome.', static fn (): Pick => $pick->for(self::question(Scoring::Probability)));
        $this->assertInvalid('"other" is not an outcome of the question "q".', static fn (): Pick => Pick::choice('other')->for($question));
        $this->assertInvalid('An outcome is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "member:1" is not.', static fn (): Pick => Pick::choice('member:1'));
    }

    public function testAForecastOnNumericOutcomes(): void
    {
        $question = new Question(QuestionKey::fromString('dice'), ['1', '2'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 10, Scoring::Probability);

        self::assertSame(['1' => 60, '2' => 40], Pick::probabilities([1 => 60, 2 => 40])->for($question)->percents);
    }

    public function testAForecast(): void
    {
        $question = self::question(Scoring::Probability);
        $pick = Pick::probabilities(['away' => 40, 'home' => 60]);

        self::assertNull($pick->choice);
        self::assertSame(['away' => 40, 'home' => 60], $pick->percents);
        self::assertSame(['home' => 60, 'away' => 40, 'draw' => 0], $pick->for($question)->percents);
        self::assertSame(['home' => 0, 'away' => 0, 'draw' => 100], Pick::probabilities(['draw' => 100, 'home' => 0])->for($question)->percents);
        self::assertSame([1 => 100], Pick::probabilities(['1' => 100])->percents);

        $this->assertInvalid('The question "q" takes one outcome, not probabilities.', static fn (): Pick => $pick->for(self::question(Scoring::Choice)));
        $this->assertInvalid('"other" is not an outcome of the question "q".', static fn (): Pick => Pick::probabilities(['home' => 50, 'other' => 50])->for($question));
        $this->assertInvalid('A probability is 0 to 100 percent; -1 is out of bounds.', static fn (): Pick => Pick::probabilities(['away' => -1, 'home' => 101]));
        $this->assertInvalid('A probability is 0 to 100 percent; 101 is out of bounds.', static fn (): Pick => Pick::probabilities(['away' => 101]));
        $this->assertInvalid('The probabilities sum to 100 percent; these sum to 99.', static fn (): Pick => Pick::probabilities(['home' => 50, 'away' => 49]));
        $this->assertInvalid('The probabilities sum to 100 percent; these sum to 0.', static fn (): Pick => Pick::probabilities([]));
        $this->assertInvalid('An outcome is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "Home" is not.', static fn (): Pick => Pick::probabilities(['Home' => 100]));
    }

    public function testAPrediction(): void
    {
        $prediction = new Prediction(PredictionId::fromString(str_repeat('a', 32)), QuestionKey::fromString('q'), ResourceReference::of('member', '1'), Pick::choice('home'), new DateTimeImmutable('2026-11-20 07:00:00-05:00'));

        self::assertSame('2026-11-20 12:00:00 UTC', $prediction->madeAt->format('Y-m-d H:i:s e'));

        $changed = $prediction->withPick(Pick::choice('away'), new DateTimeImmutable('2026-11-21 12:00:00 UTC'));
        self::assertTrue($changed->id->equals($prediction->id));
        self::assertTrue($changed->question->equals($prediction->question));
        self::assertTrue($changed->member->equals($prediction->member));
        self::assertSame('away', $changed->pick->choice);
        self::assertSame('2026-11-21 12:00:00', $changed->madeAt->format('Y-m-d H:i:s'));
    }

    private static function question(Scoring $scoring): Question
    {
        return new Question(QuestionKey::fromString('q'), ['home', 'away', 'draw'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 10, $scoring);
    }

    /**
     * @param callable(): mixed $call
     */
    private function assertInvalid(string $message, callable $call): void
    {
        try {
            $call();
            self::fail($message);
        } catch (InvalidPrediction $invalidPrediction) {
            self::assertSame($message, $invalidPrediction->getMessage());
        }
    }
}
