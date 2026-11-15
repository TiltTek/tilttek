<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Contract\Friendship;

/**
 * Friendships in memory, both ways, for the tests and the demonstrations.
 */
final class InMemoryFriendship implements Friendship
{
    /** @var array<string, true> */
    private array $pairs = [];

    public function befriend(ResourceReference $member, ResourceReference $other): void
    {
        $this->pairs[$member->toString()."\n".$other->toString()] = true;
        $this->pairs[$other->toString()."\n".$member->toString()] = true;
    }

    #[Override]
    public function areFriends(ResourceReference $member, ResourceReference $other): bool
    {
        return isset($this->pairs[$member->toString()."\n".$other->toString()]);
    }
}
