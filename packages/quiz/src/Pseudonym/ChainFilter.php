<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Pseudonym;

use Override;
use Tilttek\Quiz\Contract\PseudonymFilter;

/**
 * Allows a pseudonym that every filter allows, asked in order until one refuses: the library's short list, then the
 * application's own list, then a moderation service.
 */
final readonly class ChainFilter implements PseudonymFilter
{
    /** @var array<PseudonymFilter> */
    private array $filters;

    public function __construct(PseudonymFilter ...$filters)
    {
        $this->filters = $filters;
    }

    #[Override]
    public function allows(string $pseudonym): bool
    {
        return array_all($this->filters, static fn (PseudonymFilter $filter): bool => $filter->allows($pseudonym));
    }
}
