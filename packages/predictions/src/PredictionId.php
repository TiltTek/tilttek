<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

use Random\Randomizer;
use Tilttek\Predictions\Exception\InvalidPrediction;

/**
 * The identifier of a prediction, drawn at random when it is first made and kept when the member changes their pick:
 * the key of its movement of points carries it rather than the member.
 */
final readonly class PredictionId
{
    private function __construct(
        public string $value,
    ) {
    }

    public static function generate(Randomizer $randomizer): self
    {
        return new self($randomizer->getBytesFromString('0123456789abcdef', 32));
    }

    /**
     * @throws InvalidPrediction when the text is not 32 lower-case hexadecimal digits
     */
    public static function fromString(string $id): self
    {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidPrediction::because('A prediction identifier is 32 lower-case hexadecimal digits.');
        }

        return new self($id);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
