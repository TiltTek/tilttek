<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A scenario ended: its finale solved or, without one, every puzzle. Dispatched after the `PuzzleSolved` that ends
 * it; it names identifiers only.
 */
final readonly class ScenarioCompleted
{
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public DateTimeImmutable $at,
    ) {
    }
}
