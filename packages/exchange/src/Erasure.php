<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Phil\Resource\ResourceReference;

/**
 * What erasing a member did: the reservations deleted, and the transfers and the offers where `erased:member` now
 * stands for the member.
 */
final readonly class Erasure
{
    /**
     * The reference that stands for an erased member in the exchanges they had with other members.
     */
    public const string ERASED = 'erased:member';

    public function __construct(
        public int $reservations,
        public int $transfers,
        public int $offers,
    ) {
    }

    public static function erasedMember(): ResourceReference
    {
        return ResourceReference::fromString(self::ERASED);
    }
}
