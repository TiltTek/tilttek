<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Limiter;

use DateTimeImmutable;

/**
 * Whether one more guess is checked now and, when it is not, from when it will be.
 */
final readonly class Allowance
{
    private function __construct(
        public ?DateTimeImmutable $retryAfter,
    ) {
    }

    public static function granted(): self
    {
        return new self(null);
    }

    public static function refused(DateTimeImmutable $retryAfter): self
    {
        return new self($retryAfter);
    }

    public function allowed(): bool
    {
        return !$this->retryAfter instanceof DateTimeImmutable;
    }
}
