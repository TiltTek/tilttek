<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * `[name]`, `[name.selectUnique(3)]` or `[name.selectMany(3)]`: a section, picked once or several times.
 *
 * @internal
 */
final readonly class Reference
{
    /**
     * @param ?string $method `selectUnique`, `selectMany` or null for one pick
     */
    public function __construct(public string $name, public ?string $method = null, public int $count = 1)
    {
    }
}
