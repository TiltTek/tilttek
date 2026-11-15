<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use Phil\Resource\ResourceReference;

/**
 * What stands for an erased member in the challenges they were part of: a reference of the kind `erased`, its
 * identifier drawn at random for each challenge, so that the records an erased member leaves are not linked to one
 * another, nor to them.
 */
final class Pseudonym
{
    public const string KIND = 'erased';

    private function __construct()
    {
    }

    public static function draw(): ResourceReference
    {
        return ResourceReference::of(self::KIND, sodium_bin2hex(random_bytes(8)));
    }

    public static function isErased(ResourceReference $member): bool
    {
        return self::KIND === $member->key;
    }
}
