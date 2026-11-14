<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * `{a|b^2|c}`: one of its parts, chosen by weight.
 *
 * @internal
 */
final readonly class Choice
{
    /**
     * @param non-empty-list<Branch> $branches at least two
     */
    public function __construct(public array $branches)
    {
    }
}
