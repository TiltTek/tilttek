<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Tests\Unit\Fixture;

use Override;
use Tilttek\TableRules\Contract\VariantCatalogue;

/**
 * The variants of the tests: a system offers the variants listed for it.
 */
final readonly class Variants implements VariantCatalogue
{
    /**
     * @param array<string, list<string>> $offered by system
     */
    public function __construct(private array $offered)
    {
    }

    #[Override]
    public function has(?string $system, string $variant): bool
    {
        return null !== $system && \in_array($variant, $this->offered[$system] ?? [], true);
    }
}
