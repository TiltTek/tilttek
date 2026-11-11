<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Override;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The remainder of a value divided by a number, compared: `%2=0` matches the even values. The remainder is that of
 * the division rounded down, so it has the sign of the divisor: -1 % 2 is 1.
 */
final readonly class Modulo implements Validator
{
    use ByEachFace;

    public function __construct(
        public int $divisor,
        public Comparison $remainder,
    ) {
        if ($divisor < 1) {
            throw InvalidDefinition::because(\sprintf('A remainder is taken by a divisor of at least 1; %d is not.', $divisor));
        }
    }

    #[Override]
    public function matches(int $value): bool
    {
        $remainder = $value % $this->divisor;

        return $this->remainder->matches($remainder < 0 ? $remainder + $this->divisor : $remainder);
    }
}
