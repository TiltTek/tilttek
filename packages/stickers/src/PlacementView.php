<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

/**
 * A placement as a viewer sees it: the placement with its sticker, so that the template draws the image with its text
 * alternative and credits its licence and attribution.
 */
final readonly class PlacementView
{
    public function __construct(
        public Placement $placement,
        public Sticker $sticker,
    ) {
    }

    /**
     * The text alternative of the sticker, for the image's `alt`.
     */
    public function alt(): string
    {
        return $this->sticker->alt;
    }

    /**
     * The credit of the sticker — its attribution, then its licence —, or null when it has neither.
     */
    public function credit(): ?string
    {
        $parts = array_filter([$this->sticker->attribution, $this->sticker->licence], static fn (?string $part): bool => null !== $part);

        return [] === $parts ? null : implode(' — ', $parts);
    }
}
