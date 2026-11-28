<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * A shelf as one viewer sees it: the copies and the wishes its member shows them, and the notes for the member alone.
 */
final readonly class ShelfView
{
    /**
     * @param list<Copy> $copies in the order they were placed, without their location for another viewer
     * @param list<Wish> $wishes in the order of the wishlist
     * @param list<Note> $notes  empty for another viewer
     */
    public function __construct(
        public string $member,
        public Viewer $viewer,
        public array $copies,
        public array $wishes,
        public array $notes,
    ) {
    }
}
