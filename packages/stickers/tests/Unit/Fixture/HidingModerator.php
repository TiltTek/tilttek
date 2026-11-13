<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit\Fixture;

use Override;
use Tilttek\Stickers\Contract\PlacementModerator;
use Tilttek\Stickers\Placement;
use Tilttek\Stickers\Verdict;

/**
 * A moderator of tests: it hides the placements of the stickers it names, for review, and shows the others.
 */
final class HidingModerator implements PlacementModerator
{
    /** @var list<Placement> */
    public array $reviewed = [];

    /**
     * @param list<string> $stickers
     */
    public function __construct(private readonly array $stickers)
    {
    }

    #[Override]
    public function review(Placement $placement): Verdict
    {
        $this->reviewed[] = $placement;

        return \in_array($placement->sticker, $this->stickers, true) ? Verdict::hide('review') : Verdict::show();
    }
}
