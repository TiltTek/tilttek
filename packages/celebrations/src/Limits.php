<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * The bounds of an inbox, so that every read stays small: the pending entries of a member — 200 by default, 1 to 1000;
 * beyond, the oldest lapse —, the entries a member's inbox keeps in all — 500 by default, from the pending bound to
 * 10,000; beyond, the oldest settled are dropped — and the entries a group shows — 10 by default, 1 to 50.
 */
final readonly class Limits
{
    public const int DEFAULT_PENDING_PER_MEMBER = 200;

    public const int MAX_PENDING_PER_MEMBER = 1000;

    public const int DEFAULT_ENTRIES_PER_MEMBER = 500;

    public const int MAX_ENTRIES_PER_MEMBER = 10_000;

    public const int DEFAULT_SHOWN_PER_GROUP = 10;

    public const int MAX_SHOWN_PER_GROUP = 50;

    /** The most entries a page of the history holds. */
    public const int MAX_PAGE = 100;

    /**
     * @throws InvalidCelebration
     */
    public function __construct(
        public int $pendingPerMember = self::DEFAULT_PENDING_PER_MEMBER,
        public int $entriesPerMember = self::DEFAULT_ENTRIES_PER_MEMBER,
        public int $shownPerGroup = self::DEFAULT_SHOWN_PER_GROUP,
    ) {
        $this->check('pending entries per member', $pendingPerMember, 1, self::MAX_PENDING_PER_MEMBER);
        $this->check('entries per member', $entriesPerMember, $pendingPerMember, self::MAX_ENTRIES_PER_MEMBER);
        $this->check('entries shown per group', $shownPerGroup, 1, self::MAX_SHOWN_PER_GROUP);
    }

    private function check(string $what, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw InvalidCelebration::because(\sprintf('The limit of %s is %d to %d; it is %d.', $what, $min, $max, $value));
        }
    }
}
