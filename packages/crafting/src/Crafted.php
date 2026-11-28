<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Told once a craft was applied: identifiers only — the holder, the recipe and the key of the craft —, with its moment.
 * A listener reads the craft back from the inventory by its key when it needs the items.
 */
final readonly class Crafted
{
    public function __construct(
        public ResourceReference $holder,
        public string $recipe,
        public string $key,
        public DateTimeImmutable $at,
    ) {
    }
}
