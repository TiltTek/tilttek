<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use Tilttek\Predictions\Exception\InvalidPrediction;

/**
 * What a member predicts: one outcome for a question scored by choice, or a probability in percent for each outcome of
 * a question scored by probability, whole numbers summing to 100. An outcome left out of a forecast is given 0 %.
 */
final readonly class Pick
{
    /**
     * @param array<int|string, int> $percents
     */
    private function __construct(
        public ?string $choice,
        public array $percents,
    ) {
    }

    public static function choice(string $outcome): self
    {
        return new self(Question::checkOutcome($outcome), []);
    }

    /**
     * @param array<int|string, int> $percents
     *
     * @throws InvalidPrediction when a probability is not 0 to 100 or they do not sum to 100
     */
    public static function probabilities(array $percents): self
    {
        $sum = 0;

        foreach ($percents as $outcome => $percent) {
            Question::checkOutcome((string) $outcome);

            if ($percent < 0 || $percent > 100) {
                throw InvalidPrediction::because(\sprintf('A probability is 0 to 100 percent; %d is out of bounds.', $percent));
            }

            $sum += $percent;
        }

        if (100 !== $sum) {
            throw InvalidPrediction::because(\sprintf('The probabilities sum to 100 percent; these sum to %d.', $sum));
        }

        return new self(null, $percents);
    }

    /**
     * The pick as the question keeps it: a choice among its outcomes, or a probability for each of them in their order.
     *
     * @throws InvalidPrediction when the pick is not of the question's scoring, or names an outcome it does not have
     */
    public function for(Question $question): self
    {
        if (Scoring::Choice === $question->scoring) {
            if (null === $this->choice) {
                throw InvalidPrediction::because(\sprintf('The question "%s" takes one outcome, not probabilities.', $question->key->value));
            }

            $this->checkOutcomes([$this->choice], $question);

            return $this;
        }

        if (null !== $this->choice) {
            throw InvalidPrediction::because(\sprintf('The question "%s" takes a probability for each outcome, not one outcome.', $question->key->value));
        }

        $this->checkOutcomes(array_map(strval(...), array_keys($this->percents)), $question);
        $percents = [];

        foreach ($question->outcomes as $outcome) {
            $percents[$outcome] = $this->percents[$outcome] ?? 0;
        }

        return new self(null, $percents);
    }

    /**
     * @param list<string> $outcomes
     */
    private function checkOutcomes(array $outcomes, Question $question): void
    {
        foreach ($outcomes as $outcome) {
            if (!\in_array($outcome, $question->outcomes, true)) {
                throw InvalidPrediction::because(\sprintf('"%s" is not an outcome of the question "%s".', $outcome, $question->key->value));
            }
        }
    }
}
