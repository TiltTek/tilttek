<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Predictions\Exception\InvalidPrediction;

/**
 * A question about a dated outcome of an event — who wins the final, whether the campaign ends by its deadline —, with
 * its possible outcomes, the moment predictions close, how they score and the points at stake for each member. The
 * outcomes are the application's keys of the event's results, such as `home`, `away` and `draw`; they hold no colon,
 * so that a member's reference is never an outcome: a question is never about a person. The moments are kept in UTC.
 */
final readonly class Question
{
    public const int MIN_OUTCOMES = 2;

    public const int MAX_OUTCOMES = 32;

    public const int MAX_POINTS = 1_000_000;

    public DateTimeImmutable $closesAt;

    public ?DateTimeImmutable $closedAt;

    /**
     * @param list<string> $outcomes
     */
    public function __construct(
        public QuestionKey $key,
        public array $outcomes,
        DateTimeImmutable $closesAt,
        public int $points,
        public Scoring $scoring = Scoring::Choice,
        public QuestionStatus $status = QuestionStatus::Open,
        public ?string $outcome = null,
        ?DateTimeImmutable $closedAt = null,
    ) {
        if (\count($outcomes) < self::MIN_OUTCOMES || \count($outcomes) > self::MAX_OUTCOMES) {
            throw InvalidPrediction::because(\sprintf('A question has %d to %d outcomes; this one has %d.', self::MIN_OUTCOMES, self::MAX_OUTCOMES, \count($outcomes)));
        }

        foreach ($outcomes as $candidate) {
            self::checkOutcome($candidate);
        }

        if (\count(array_unique($outcomes)) !== \count($outcomes)) {
            throw InvalidPrediction::because('A question names each outcome once.');
        }

        if ($points < 1 || $points > self::MAX_POINTS) {
            throw InvalidPrediction::because(\sprintf('A question gives 1 to %d points; %d is out of bounds.', self::MAX_POINTS, $points));
        }

        if ((QuestionStatus::Open === $status) !== (!$closedAt instanceof DateTimeImmutable)) {
            throw InvalidPrediction::because('A question is closed at a moment exactly when it is resolved or cancelled.');
        }

        if ((QuestionStatus::Resolved === $status) !== (null !== $outcome)) {
            throw InvalidPrediction::because('A question has an outcome exactly when it is resolved.');
        }

        if (null !== $outcome && !\in_array($outcome, $outcomes, true)) {
            throw InvalidPrediction::because(\sprintf('"%s" is not an outcome of the question "%s".', $outcome, $key->value));
        }

        $utc = new DateTimeZone('UTC');
        $this->closesAt = $closesAt->setTimezone($utc);
        $this->closedAt = $closedAt?->setTimezone($utc);
    }

    /**
     * @throws InvalidPrediction when the outcome is not 1 to 64 lower-case letters, digits, "_", "." or "-"
     */
    public static function checkOutcome(string $outcome): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $outcome)) {
            throw InvalidPrediction::because(\sprintf('An outcome is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $outcome));
        }

        return $outcome;
    }

    /**
     * Whether the question takes predictions at the moment: open, and before its deadline.
     */
    public function takesPredictionsAt(DateTimeImmutable $moment): bool
    {
        return QuestionStatus::Open === $this->status && $moment < $this->closesAt;
    }

    /**
     * Whether the other question asks the same: the same outcomes in the same order, deadline, points and scoring.
     */
    public function sameAs(self $other): bool
    {
        return $this->key->equals($other->key)
            && $this->outcomes === $other->outcomes
            && $this->closesAt->format('U') === $other->closesAt->format('U')
            && $this->points === $other->points
            && $this->scoring === $other->scoring;
    }

    public function resolved(string $outcome, DateTimeImmutable $at): self
    {
        return new self($this->key, $this->outcomes, $this->closesAt, $this->points, $this->scoring, QuestionStatus::Resolved, $outcome, $at);
    }

    public function cancelled(DateTimeImmutable $at): self
    {
        return new self($this->key, $this->outcomes, $this->closesAt, $this->points, $this->scoring, QuestionStatus::Cancelled, null, $at);
    }

    /**
     * The points the pick earns once the question is resolved; 0 while it is not.
     */
    public function pointsFor(Pick $pick): int
    {
        if (null === $this->outcome) {
            return 0;
        }

        return match ($this->scoring) {
            Scoring::Choice => $pick->choice === $this->outcome ? $this->points : 0,
            Scoring::Probability => Brier::points($this->points, Brier::score($pick->percents, $this->outcome)),
        };
    }
}
