<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application\Exception;

use Override;
use RuntimeException;

/**
 * The groups of an expression would take too long to search over the samples of an estimate: it is refused before any
 * die is rolled.
 */
final class GroupsTooCostly extends RuntimeException implements Refusal
{
    public static function of(string $expression, int $work, int $limit): self
    {
        return new self(\sprintf('The groups of "%s" would take %d steps to search over the samples of its estimate; at most %d are taken.', $expression, $work, $limit));
    }

    #[Override]
    public function key(): string
    {
        return 'refusal.groups';
    }

    #[Override]
    public function parameters(): array
    {
        return [];
    }
}
