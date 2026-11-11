<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * A mark of a critical success (`cs`, `cs>19`) or failure (`cf`, `cf1`), for display: it changes no value. Without a
 * validator, a critical success is the highest face as it fell and a critical failure the lowest.
 */
final readonly class Critical
{
    public function __construct(
        public ?Validator $on = null,
    ) {
    }

    public function marks(int $face, int $bound): bool
    {
        return $this->on?->matches($face) ?? $face === $bound;
    }
}
