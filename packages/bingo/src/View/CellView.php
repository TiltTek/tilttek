<?php

declare(strict_types=1);

namespace Tilttek\Bingo\View;

/**
 * A cell as `templates/card.html.twig` shows it: its square and label, or the free centre, and whether it is ticked.
 */
final readonly class CellView
{
    public function __construct(
        public ?string $square,
        public ?string $label,
        public bool $ticked,
    ) {
    }

    public function isFree(): bool
    {
        return null === $this->square;
    }
}
