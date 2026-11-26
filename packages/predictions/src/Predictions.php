<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Random\Randomizer;
use Tilttek\Points\Account;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Points;
use Tilttek\Predictions\Contract\PredictionStore;
use Tilttek\Predictions\Exception\InvalidPrediction;
use Tilttek\Predictions\Exception\QuestionClosed;
use Tilttek\Predictions\Exception\UnknownQuestion;

/**
 * The predictions of an application, in one object: questions on dated outcomes, the members' picks until the
 * deadline, and the settlement in points once the application declares the outcome. Nothing is staked: a member never
 * gives points to predict, and a wrong pick costs nothing.
 *
 * ```php
 * $predictions->ask('final:2026', ['home', 'away'], new DateTimeImmutable('2026-12-05 19:00 UTC'), 50);
 * $predictions->predict('final:2026', $member, Pick::choice('home'));
 * $predictions->resolve('final:2026', 'home');        // pays the right picks, once
 * ```
 *
 * The points are given by `tilttek/points`, under a key built from each prediction's identifier: a settlement run
 * again, or by two processes at once, gives each prediction's points once.
 */
final readonly class Predictions
{
    public const int MAX_READ = 100;

    public const int DEFAULT_BATCH_SIZE = 500;

    /**
     * @param ?string $currency  the currency of `tilttek/points`, its default when null
     * @param string  $source    the issuance account the points come from
     * @param int     $batchSize how many predictions a settlement reads at once
     */
    public function __construct(
        private PredictionStore $store,
        private Points $points,
        private ClockInterface $clock,
        private ?string $currency = null,
        private string $source = 'predictions',
        private Randomizer $randomizer = new Randomizer(),
        private int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
        try {
            if (null !== $currency) {
                Currency::checkCode($currency);
            }

            Account::issuance($source);
        } catch (InvalidPoints $invalidPoints) {
            throw InvalidPrediction::because($invalidPoints->getMessage());
        }

        if ($batchSize < 1) {
            throw InvalidPrediction::because(\sprintf('A batch holds at least one prediction; %d is not a batch size.', $batchSize));
        }
    }

    /**
     * Asks a question that takes predictions until its deadline. The same question asked again is returned as it is.
     *
     * @param list<string> $outcomes
     *
     * @throws InvalidPrediction when a value is out of bounds, the deadline is not in the future, or a question is
     *                           asked under the key differently
     */
    public function ask(string $key, array $outcomes, DateTimeImmutable $closesAt, int $points, Scoring $scoring = Scoring::Choice): Question
    {
        $question = new Question(QuestionKey::fromString($key), $outcomes, $closesAt, $points, $scoring);
        $kept = $this->store->question($question->key);

        if (!$kept instanceof Question) {
            if ($question->closesAt <= $this->clock->now()) {
                throw InvalidPrediction::because(\sprintf('The question "%s" closes in the past.', $key));
            }

            $kept = $this->store->addQuestion($question);
        }

        if (!$kept->sameAs($question)) {
            throw InvalidPrediction::because(\sprintf('A question is asked under the key "%s" differently.', $key));
        }

        return $kept;
    }

    public function question(string $key): ?Question
    {
        return $this->store->question(QuestionKey::fromString($key));
    }

    /**
     * Records the member's pick, in place of the one they made before, while the question takes predictions.
     *
     * @throws UnknownQuestion
     * @throws QuestionClosed    when the deadline has passed or the question is resolved or cancelled
     * @throws InvalidPrediction when the pick does not fit the question
     */
    public function predict(string $question, ResourceReference $member, Pick $pick): Prediction
    {
        $asked = $this->asked($question);
        $now = $this->clock->now();

        if (!$asked->takesPredictionsAt($now)) {
            throw QuestionClosed::toPredictions($asked->key);
        }

        $prediction = new Prediction(PredictionId::generate($this->randomizer), $asked->key, $member, $pick->for($asked), $now);

        return $this->store->save($prediction, $now) ?? throw QuestionClosed::toPredictions($asked->key);
    }

    public function predictionOf(string $question, ResourceReference $member): ?Prediction
    {
        return $this->store->prediction(QuestionKey::fromString($question), $member);
    }

    /**
     * The member's predictions, in the order they were first made.
     *
     * @return list<Prediction>
     *
     * @throws InvalidPrediction when the offset is negative or the limit is not 1 to 100
     */
    public function predictionsOf(ResourceReference $member, int $offset = 0, int $limit = 50): array
    {
        if ($offset < 0 || $limit < 1 || $limit > self::MAX_READ) {
            throw InvalidPrediction::because(\sprintf('A read starts at 0 or later and holds 1 to %d predictions.', self::MAX_READ));
        }

        return $this->store->ofMember($member, $offset, $limit);
    }

    /**
     * Declares the question's outcome, which closes it to predictions, and settles it. Declared again with the same
     * outcome, it settles again, paying nothing twice.
     *
     * @throws UnknownQuestion
     * @throws InvalidPrediction when the outcome is not one of the question's
     * @throws QuestionClosed    when the question is cancelled, or resolved with another outcome
     * @throws MovementConflict  when a prediction's key is written in the ledger for other points
     */
    public function resolve(string $question, string $outcome): Settlement
    {
        $asked = $this->asked($question);

        if (!\in_array($outcome, $asked->outcomes, true)) {
            throw InvalidPrediction::because(\sprintf('"%s" is not an outcome of the question "%s".', $outcome, $question));
        }

        $closed = $this->store->close($asked->key, QuestionStatus::Resolved, $outcome, $this->clock->now()) ?? throw UnknownQuestion::named($asked->key);

        if (QuestionStatus::Cancelled === $closed->status) {
            throw QuestionClosed::cancelled($closed->key);
        }

        if ($closed->outcome !== $outcome) {
            throw QuestionClosed::resolved($closed->key);
        }

        return $this->settle($question);
    }

    /**
     * Cancels the question: it takes no more predictions and pays nothing. Cancelled again, it is returned as it is.
     *
     * @throws UnknownQuestion
     * @throws QuestionClosed  when the question is resolved
     */
    public function cancel(string $question): Question
    {
        $asked = $this->asked($question);
        $closed = $this->store->close($asked->key, QuestionStatus::Cancelled, null, $this->clock->now()) ?? throw UnknownQuestion::named($asked->key);

        if (QuestionStatus::Resolved === $closed->status) {
            throw QuestionClosed::resolved($closed->key);
        }

        return $closed;
    }

    /**
     * Pays the predictions of the resolved question, each once: the task to run again after a settlement cut short.
     * A cancelled question settles to nothing.
     *
     * @throws UnknownQuestion
     * @throws QuestionClosed   when the question is still open
     * @throws MovementConflict when a prediction's key is written in the ledger for other points
     */
    public function settle(string $question): Settlement
    {
        $asked = $this->asked($question);

        if (QuestionStatus::Cancelled === $asked->status) {
            return new Settlement($asked);
        }

        if (QuestionStatus::Resolved !== $asked->status) {
            throw QuestionClosed::unresolved($asked->key);
        }

        $read = 0;
        $after = null;
        $rewarded = 0;
        $points = 0;
        $written = 0;

        do {
            $batch = $this->store->predictions($asked->key, $after, $this->batchSize);

            foreach ($batch as $prediction) {
                $after = $prediction->id;
                $earned = $asked->pointsFor($prediction->pick);

                if ($earned > 0) {
                    ++$rewarded;
                    $points += $earned;
                    $written += $this->points->grant($prediction->member, $earned, 'prediction:'.$prediction->id->value, $this->source, $this->currency)->new ? 1 : 0;
                }
            }

            $read += \count($batch);
        } while (\count($batch) === $this->batchSize);

        return new Settlement($asked, $read, $rewarded, $points, $written);
    }

    /**
     * Erases the member's predictions: none names them afterwards. Their points are erased by `tilttek/points`.
     */
    public function erase(ResourceReference $member): int
    {
        return $this->store->eraseMember($member);
    }

    /**
     * @throws UnknownQuestion
     */
    private function asked(string $question): Question
    {
        $key = QuestionKey::fromString($question);

        return $this->store->question($key) ?? throw UnknownQuestion::named($key);
    }
}
