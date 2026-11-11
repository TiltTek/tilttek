<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;

/**
 * A fixed value, as written.
 */
final readonly class ConstantResult implements NodeResult
{
    public function __construct(
        private int $value,
    ) {
    }

    #[Override]
    public function value(): int
    {
        return $this->value;
    }

    #[Override]
    public function output(): int
    {
        return $this->value;
    }

    #[Override]
    public function sets(): array
    {
        return [];
    }

    /**
     * @return array{type: 'constant', value: int}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'constant', 'value' => $this->value];
    }
}
