<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Override;

/**
 * Two validators joined: `>2&<6`, `1|6`, `%2=0^>4`.
 */
final readonly class Composite implements Validator
{
    use ByEachFace {
        matchesAll as private matchesEachFace;
    }

    public function __construct(
        public Junction $junction,
        public Validator $left,
        public Validator $right,
    ) {
    }

    #[Override]
    public function matches(int $value): bool
    {
        return match ($this->junction) {
            Junction::And => $this->left->matches($value) && $this->right->matches($value),
            Junction::Or => $this->left->matches($value) || $this->right->matches($value),
            Junction::Xor => $this->left->matches($value) !== $this->right->matches($value),
        };
    }

    #[Override]
    public function matchesAll(int $lowest, int $highest): bool
    {
        if (Junction::And === $this->junction) {
            return $this->left->matchesAll($lowest, $highest) && $this->right->matchesAll($lowest, $highest);
        }

        return $this->matchesEachFace($lowest, $highest);
    }
}
