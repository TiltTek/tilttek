<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Psr\Clock\ClockInterface;
use Tilttek\GameCatalog\Contract\CatalogStore;
use Tilttek\GameCatalog\Contract\PlayHistory;
use Tilttek\GameCatalog\Contract\ReviewSystem;
use Tilttek\GameCatalog\Exception\EntryNotFound;

/**
 * The experience of the author beside each review of a game: the games played and run, read from the plays when the
 * review is written, and frozen there. The reviews themselves are kept by the application's review system.
 */
final readonly class Reviews
{
    public function __construct(
        private CatalogStore $store,
        private PlayHistory $plays,
        private ReviewSystem $reviews,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The experience of the author of the review: frozen the first time it is asked, and the same after, whatever the
     * author plays since.
     *
     * @throws EntryNotFound when no entry has the identifier
     */
    public function experience(string $review, string $entry, string $member): Experience
    {
        $kept = $this->reviews->experience($review);

        if ($kept instanceof Experience) {
            return $kept;
        }

        $this->store->entry($entry);
        $experience = new Experience($this->plays->played($member, $entry), $this->plays->ran($member, $entry), $this->clock->now());
        $this->reviews->keepExperience($review, $entry, $member, $experience);

        return $experience;
    }
}
