<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Points;
use Tilttek\Predictions\Contract\PredictionStore;
use Tilttek\Predictions\Exception\InvalidPrediction;
use Tilttek\Predictions\Exception\QuestionClosed;
use Tilttek\Predictions\Exception\UnknownQuestion;
use Tilttek\Predictions\Pick;
use Tilttek\Predictions\Predictions;
use Tilttek\Predictions\Question;
use Tilttek\Predictions\QuestionKey;
use Tilttek\Predictions\QuestionStatus;
use Tilttek\Predictions\Scoring;
use Tilttek\Predictions\Settlement;
use Tilttek\Predictions\Store\InMemoryPredictionStore;
use Tilttek\Predictions\Tests\Unit\Fixture\InterleavingPredictionStore;
use Tilttek\Predictions\Tests\Unit\Fixture\VanishingQuestionStore;

#[CoversClass(Predictions::class)]
#[CoversClass(Settlement::class)]
#[CoversClass(QuestionClosed::class)]
#[CoversClass(UnknownQuestion::class)]
final class PredictionsTest extends TestCase
{
    private InMemoryPredictionStore $store;

    private InMemoryLedger $ledger;

    private MockClock $clock;

    private Points $points;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryPredictionStore();
        $this->ledger = new InMemoryLedger();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-20 12:00:00 UTC'));
        $this->points = new Points($this->ledger, $this->clock, new Currencies(new Currency('points'), new Currency('stars')));
    }

    public function testAskingAQuestion(): void
    {
        $question = $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 14:00:00-05:00'), 50);

        self::assertSame('final:2026', $question->key->value);
        self::assertSame(['home', 'away'], $question->outcomes);
        self::assertSame('2026-12-05 19:00:00', $question->closesAt->format('Y-m-d H:i:s'));
        self::assertSame(50, $question->points);
        self::assertSame(Scoring::Choice, $question->scoring);
        self::assertSame(QuestionStatus::Open, $question->status);
        self::assertSame($question, $this->predictions()->question('final:2026'));
        self::assertNull($this->predictions()->question('final:2027'));
    }

    public function testAskingTheSameQuestionAgainIsTheSameQuestion(): void
    {
        $question = $this->ask();
        $this->clock->modify('+30 days');

        self::assertSame($question, $this->ask());
    }

    public function testAQuestionAskedDifferentlyIsRefused(): void
    {
        $this->ask();

        foreach ([
            fn (): mixed => $this->predictions()->ask('final:2026', ['home', 'away', 'draw'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 50),
            fn (): mixed => $this->predictions()->ask('final:2026', ['away', 'home'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 50),
            fn (): mixed => $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00:01 UTC'), 50),
            fn (): mixed => $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 51),
            fn (): mixed => $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 50, Scoring::Probability),
        ] as $index => $ask) {
            try {
                $ask();
                self::fail((string) $index);
            } catch (InvalidPrediction $invalid) {
                self::assertSame('A question is asked under the key "final:2026" differently.', $invalid->getMessage());
            }
        }

        self::assertSame($this->ask(), $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 14:00:00-05:00'), 50));
    }

    public function testAQuestionClosesInTheFuture(): void
    {
        self::assertNotNull($this->predictions()->ask('soon', ['a', 'b'], new DateTimeImmutable('2026-11-20 12:00:01 UTC'), 1));

        $this->expectExceptionObject(InvalidPrediction::because('The question "now" closes in the past.'));
        $this->predictions()->ask('now', ['a', 'b'], new DateTimeImmutable('2026-11-20 12:00:00 UTC'), 1);
    }

    public function testPredictingAndChangingThePick(): void
    {
        $this->ask();
        $first = $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->clock->modify('+1 day');

        $changed = $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('away'));

        self::assertTrue($first->id->equals($changed->id));
        self::assertSame('away', $changed->pick->choice);
        self::assertSame('2026-11-21 12:00:00', $changed->madeAt->format('Y-m-d H:i:s'));
        self::assertSame($changed, $this->predictions()->predictionOf('final:2026', $this->member(1)));
        self::assertSame([$changed], $this->predictions()->predictionsOf($this->member(1)));
        self::assertNull($this->predictions()->predictionOf('final:2026', $this->member(2)));
    }

    public function testPredictionsCloseAtTheDeadline(): void
    {
        $this->ask();
        $this->clock->modify('2026-12-05 18:59:59 UTC');
        self::assertNotNull($this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home')));

        $this->clock->modify('2026-12-05 19:00:00 UTC');
        $this->expectExceptionObject(QuestionClosed::toPredictions(QuestionKey::fromString('final:2026')));
        $this->predictions()->predict('final:2026', $this->member(2), Pick::choice('home'));
    }

    public function testAPredictionWrittenAfterAResolutionThatSlippedInIsRefused(): void
    {
        $this->ask();
        $store = new InterleavingPredictionStore($this->store, 'save', fn (): Settlement => $this->predictions()->resolve('final:2026', 'home'));

        try {
            $this->predictions($store)->predict('final:2026', $this->member(1), Pick::choice('home'));
            self::fail('The prediction was written.');
        } catch (QuestionClosed $questionClosed) {
            self::assertSame('The question "final:2026" takes no more predictions.', $questionClosed->getMessage());
        }

        self::assertNull($this->store->prediction(QuestionKey::fromString('final:2026'), $this->member(1)));
    }

    public function testAPickMustFitTheQuestion(): void
    {
        $this->ask();

        $this->expectExceptionObject(InvalidPrediction::because('"draw" is not an outcome of the question "final:2026".'));
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('draw'));
    }

    public function testAnUnknownQuestion(): void
    {
        $this->expectExceptionObject(UnknownQuestion::named(QuestionKey::fromString('nope')));
        $this->predictions()->predict('nope', $this->member(1), Pick::choice('home'));
    }

    public function testResolvingPaysTheRightPicks(): void
    {
        $this->ask();
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->predictions()->predict('final:2026', $this->member(2), Pick::choice('away'));
        $this->predictions()->predict('final:2026', $this->member(3), Pick::choice('home'));
        $this->clock->modify('2026-12-05 21:00:00 UTC');

        $settlement = $this->predictions()->resolve('final:2026', 'home');

        self::assertSame(QuestionStatus::Resolved, $settlement->question->status);
        self::assertSame('home', $settlement->question->outcome);
        self::assertSame('2026-12-05 21:00:00', $settlement->question->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame([3, 2, 100, 2], [$settlement->predictions, $settlement->rewarded, $settlement->points, $settlement->written]);
        self::assertSame(50, $this->points->balance($this->member(1)));
        self::assertSame(0, $this->points->balance($this->member(2)));
        self::assertSame(50, $this->points->balance($this->member(3)));

        $prediction = $this->store->prediction(QuestionKey::fromString('final:2026'), $this->member(1));
        $movement = $this->ledger->find(MovementKey::fromString('prediction:'.$prediction?->id->value))?->movement;
        self::assertSame('issuance:predictions', $movement?->debit->toString());
        self::assertSame('member:member:1', $movement->credit->toString());
    }

    public function testAResolutionBeforeTheDeadlineClosesThePredictions(): void
    {
        $this->ask();
        $this->predictions()->resolve('final:2026', 'away');

        $this->expectException(QuestionClosed::class);
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('away'));
    }

    public function testTheCurrencyAndTheSource(): void
    {
        $this->ask();
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));

        $this->predictions(currency: 'stars', source: 'contest:final')->resolve('final:2026', 'home');

        self::assertSame(50, $this->points->balance($this->member(1), 'stars'));
        self::assertSame('issuance:contest:final', $this->points->history($this->member(1), 'stars')[0]->movement->debit->toString());
    }

    public function testAForecastIsScoredByBrier(): void
    {
        $this->predictions()->ask('campaign:12:end', ['on-time', 'late'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 100, Scoring::Probability);
        $this->predictions()->predict('campaign:12:end', $this->member(1), Pick::probabilities(['on-time' => 70, 'late' => 30]));
        $this->predictions()->predict('campaign:12:end', $this->member(2), Pick::probabilities(['late' => 100]));
        $this->predictions()->predict('campaign:12:end', $this->member(3), Pick::probabilities(['on-time' => 50, 'late' => 50]));

        $settlement = $this->predictions()->resolve('campaign:12:end', 'on-time');

        self::assertSame([3, 2, 166, 2], [$settlement->predictions, $settlement->rewarded, $settlement->points, $settlement->written]);
        self::assertSame(91, $this->points->balance($this->member(1)));
        self::assertSame(0, $this->points->balance($this->member(2)));
        self::assertSame(75, $this->points->balance($this->member(3)));
        self::assertSame(['on-time' => 0, 'late' => 100], $this->predictions()->predictionOf('campaign:12:end', $this->member(2))?->pick->percents);
    }

    public function testASettlementRunAgainPaysNothingTwice(): void
    {
        $this->ask();
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->predictions()->resolve('final:2026', 'home');

        $again = $this->predictions()->resolve('final:2026', 'home');
        $settled = $this->predictions()->settle('final:2026');

        self::assertSame([1, 1, 50, 0], [$again->predictions, $again->rewarded, $again->points, $again->written]);
        self::assertSame(0, $settled->written);
        self::assertSame(50, $this->points->balance($this->member(1)));
        self::assertCount(1, $this->points->history($this->member(1)));
    }

    public function testTwoSettlementsAtOncePayEachPredictionOnce(): void
    {
        $this->ask();
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->predictions()->predict('final:2026', $this->member(2), Pick::choice('home'));
        $this->store->close(QuestionKey::fromString('final:2026'), QuestionStatus::Resolved, 'home', $this->clock->now());
        $other = null;
        $store = new InterleavingPredictionStore($this->store, 'predictions', function () use (&$other): void {
            $other = $this->predictions()->settle('final:2026');
        });

        $settlement = $this->predictions($store)->settle('final:2026');

        self::assertSame(2, $other?->written);
        self::assertSame(0, $settlement->written);
        self::assertSame(50, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));
    }

    public function testASettlementReadsEveryPage(): void
    {
        $this->ask();

        foreach (range(1, 5) as $member) {
            $this->predictions()->predict('final:2026', $this->member($member), Pick::choice(1 === $member % 2 ? 'home' : 'away'));
        }

        $this->store->close(QuestionKey::fromString('final:2026'), QuestionStatus::Resolved, 'home', $this->clock->now());
        $settlement = $this->predictions(batchSize: 2)->settle('final:2026');

        self::assertSame([5, 3, 150, 3], [$settlement->predictions, $settlement->rewarded, $settlement->points, $settlement->written]);
        self::assertSame([5, 3], [$this->predictions(batchSize: 1)->settle('final:2026')->predictions, $this->predictions(batchSize: 5)->settle('final:2026')->rewarded]);
    }

    public function testAMemberErasedDuringTheSettlementShiftsNoOtherPrediction(): void
    {
        $this->ask();

        foreach (range(1, 4) as $member) {
            $this->predictions()->predict('final:2026', $this->member($member), Pick::choice('home'));
        }

        $this->store->close(QuestionKey::fromString('final:2026'), QuestionStatus::Resolved, 'home', $this->clock->now());
        $first = $this->store->predictions(QuestionKey::fromString('final:2026'), null, 1)[0];
        $store = new InterleavingPredictionStore($this->store, 'predictions', fn (): int => $this->store->eraseMember($first->member));

        $settlement = $this->predictions($store, batchSize: 2)->settle('final:2026');

        self::assertSame(4, $settlement->predictions);
        self::assertSame(200, array_sum(array_map(fn (int $member): int => $this->points->balance($this->member($member)), range(1, 4))));
    }

    public function testAQuestionCannotBeResolvedTwoWays(): void
    {
        $this->ask();
        $this->predictions()->resolve('final:2026', 'home');

        $this->expectExceptionObject(QuestionClosed::resolved(QuestionKey::fromString('final:2026')));
        $this->predictions()->resolve('final:2026', 'away');
    }

    public function testResolvingWithAnOutcomeTheQuestionHasNot(): void
    {
        $this->ask();

        $this->expectExceptionObject(InvalidPrediction::because('"draw" is not an outcome of the question "final:2026".'));
        $this->predictions()->resolve('final:2026', 'draw');
    }

    public function testACancellationPaysNothing(): void
    {
        $this->ask();
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));

        $cancelled = $this->predictions()->cancel('final:2026');

        self::assertSame(QuestionStatus::Cancelled, $cancelled->status);
        self::assertSame($cancelled, $this->predictions()->cancel('final:2026'));
        $settlement = $this->predictions()->settle('final:2026');
        self::assertSame([0, 0, 0, 0], [$settlement->predictions, $settlement->rewarded, $settlement->points, $settlement->written]);
        self::assertSame($cancelled, $settlement->question);
        self::assertSame(0, $this->points->balance($this->member(1)));

        try {
            $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
            self::fail('A cancelled question took a prediction.');
        } catch (QuestionClosed) {
        }

        $this->expectExceptionObject(QuestionClosed::cancelled(QuestionKey::fromString('final:2026')));
        $this->predictions()->resolve('final:2026', 'home');
    }

    public function testAResolvedQuestionCannotBeCancelled(): void
    {
        $this->ask();
        $this->predictions()->resolve('final:2026', 'home');

        $this->expectExceptionObject(QuestionClosed::resolved(QuestionKey::fromString('final:2026')));
        $this->predictions()->cancel('final:2026');
    }

    public function testAnOpenQuestionHasNothingToSettle(): void
    {
        $this->ask();

        $this->expectExceptionObject(QuestionClosed::unresolved(QuestionKey::fromString('final:2026')));
        $this->predictions()->settle('final:2026');
    }

    public function testAQuestionDeletedMeanwhileIsUnknown(): void
    {
        $this->ask();
        $store = new VanishingQuestionStore($this->store);

        foreach ([fn (): mixed => $this->predictions($store)->resolve('final:2026', 'home'), fn (): mixed => $this->predictions($store)->cancel('final:2026')] as $call) {
            try {
                $call();
                self::fail('The question was found.');
            } catch (UnknownQuestion $unknown) {
                self::assertSame('No question is asked under the key "final:2026".', $unknown->getMessage());
            }
        }
    }

    public function testAKeyWrittenForOtherPointsIsAConflict(): void
    {
        $this->ask();
        $prediction = $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->points->grant($this->member(1), 7, 'prediction:'.$prediction->id->value);

        $this->expectException(MovementConflict::class);
        $this->predictions()->resolve('final:2026', 'home');
    }

    public function testErasingAMemberLeavesNoPrediction(): void
    {
        $this->ask();
        $this->predictions()->ask('other', ['a', 'b'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 5);
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('home'));
        $this->predictions()->predict('other', $this->member(1), Pick::choice('a'));
        $this->predictions()->predict('final:2026', $this->member(2), Pick::choice('home'));

        self::assertSame(2, $this->predictions()->erase($this->member(1)));

        self::assertSame([], $this->predictions()->predictionsOf($this->member(1)));
        self::assertNull($this->predictions()->predictionOf('final:2026', $this->member(1)));
        self::assertSame(1, $this->predictions()->resolve('final:2026', 'home')->predictions);
        self::assertSame(0, $this->points->balance($this->member(1)));
    }

    public function testReadingAMembersPredictions(): void
    {
        $made = [];

        foreach (['a', 'b', 'c'] as $key) {
            $this->predictions()->ask($key, ['x', 'y'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 5);
            $made[] = $this->predictions()->predict($key, $this->member(1), Pick::choice('x'));
        }

        self::assertSame([$made[1], $made[2]], $this->predictions()->predictionsOf($this->member(1), 1, 100));
        self::assertSame([$made[0]], $this->predictions()->predictionsOf($this->member(1), 0, 1));

        foreach ([[-1, 1], [0, 0], [0, 101]] as [$offset, $limit]) {
            try {
                $this->predictions()->predictionsOf($this->member(1), $offset, $limit);
                self::fail(\sprintf('%d, %d', $offset, $limit));
            } catch (InvalidPrediction $invalid) {
                self::assertSame('A read starts at 0 or later and holds 1 to 100 predictions.', $invalid->getMessage());
            }
        }
    }

    public function testAReadHoldsFiftyPredictionsByDefault(): void
    {
        for ($number = 1; $number <= 51; ++$number) {
            $this->predictions()->ask('q'.$number, ['x', 'y'], new DateTimeImmutable('2026-12-01 00:00:00 UTC'), 5);
            $this->predictions()->predict('q'.$number, $this->member(1), Pick::choice('x'));
        }

        self::assertCount(50, $this->predictions()->predictionsOf($this->member(1)));
    }

    public function testAClosedQuestionRefusesBeforeLookingAtThePick(): void
    {
        $this->ask();
        $this->clock->modify('2026-12-05 19:00:00 UTC');

        $this->expectExceptionObject(QuestionClosed::toPredictions(QuestionKey::fromString('final:2026')));
        $this->predictions()->predict('final:2026', $this->member(1), Pick::choice('draw'));
    }

    public function testAnUnknownOutcomeNeverReachesTheStore(): void
    {
        $this->ask();
        $store = new InterleavingPredictionStore($this->store, 'close', static fn (): never => self::fail('The store was asked to close the question.'));

        $this->expectExceptionObject(InvalidPrediction::because('"draw" is not an outcome of the question "final:2026".'));
        $this->predictions($store)->resolve('final:2026', 'draw');
    }

    public function testTheSettingsAreChecked(): void
    {
        $cases = [
            '"Stars" is not a currency code: 1 to 32 lower-case letters, digits and "_", starting with a letter.' => fn (): Predictions => $this->predictions(currency: 'Stars'),
            '"Predictions" is not an account name: 1 to 255 lower-case letters, digits, "_", ".", ":" or "-", starting with a letter or a digit.' => fn (): Predictions => $this->predictions(source: 'Predictions'),
            'A batch holds at least one prediction; 0 is not a batch size.' => fn (): Predictions => $this->predictions(batchSize: 0),
        ];

        foreach ($cases as $message => $build) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidPrediction $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertInstanceOf(Predictions::class, $this->predictions(batchSize: 1));
    }

    private function ask(): Question
    {
        return $this->predictions()->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00:00 UTC'), 50);
    }

    private function predictions(?PredictionStore $store = null, ?string $currency = null, string $source = 'predictions', int $batchSize = Predictions::DEFAULT_BATCH_SIZE): Predictions
    {
        return new Predictions($store ?? $this->store, $this->points, $this->clock, $currency, $source, new Randomizer(), $batchSize);
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
