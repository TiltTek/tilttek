<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use InvalidArgumentException;
use Override;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Validator;

/**
 * How many dice show a face, or a value that matches a validator, compared with a number: at least one 1, exactly two
 * sixes, at least three dice of 5 or more.
 */
final readonly class Count implements Event
{
    /**
     * @throws InvalidArgumentException when the number of dice is negative
     */
    public function __construct(
        public Validator $faces,
        public Comparator $comparator,
        public int $dice,
    ) {
        if ($dice < 0) {
            throw new InvalidArgumentException(\sprintf('An event counts zero dice or more; %d is not.', $dice));
        }
    }

    public static function atLeastOne(int|Validator $faces): self
    {
        return self::atLeast(1, $faces);
    }

    public static function atLeast(int $dice, int|Validator $faces): self
    {
        return new self(self::validator($faces), Comparator::GreaterOrEqual, $dice);
    }

    public static function atMost(int $dice, int|Validator $faces): self
    {
        return new self(self::validator($faces), Comparator::LessOrEqual, $dice);
    }

    public static function exactly(int $dice, int|Validator $faces): self
    {
        return new self(self::validator($faces), Comparator::Equal, $dice);
    }

    #[Override]
    public function matches(Faces $faces): bool
    {
        return new Comparison($this->comparator, $this->dice)->matches($faces->matching($this->faces));
    }

    private static function validator(int|Validator $faces): Validator
    {
        return \is_int($faces) ? new Comparison(Comparator::Equal, $faces) : $faces;
    }
}
