<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application\Exception;

use Override;
use RuntimeException;

/**
 * A distribution has more values than the bundle draws: its table and its chart would be too long to send.
 */
final class TooManyOutcomes extends RuntimeException implements Refusal
{
    public function __construct(
        string $expression,
        private readonly int $outcomes,
        private readonly int $limit,
    ) {
        parent::__construct(\sprintf('The distribution of "%s" has %d values; at most %d are drawn.', $expression, $outcomes, $limit));
    }

    public static function of(string $expression, int $outcomes, int $limit): self
    {
        return new self($expression, $outcomes, $limit);
    }

    #[Override]
    public function key(): string
    {
        return 'refusal.outcomes';
    }

    #[Override]
    public function parameters(): array
    {
        return ['%outcomes%' => $this->outcomes, '%limit%' => $this->limit];
    }
}
