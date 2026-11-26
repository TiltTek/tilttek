<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

/**
 * The role of a member in a guild: one founder, who holds every right and names the administrators; administrators,
 * who approve requests, remove members, edit the guild and open its goals; members.
 */
enum Role: string
{
    case Founder = 'founder';

    case Administrator = 'administrator';

    case Member = 'member';

    /**
     * Whether this role approves requests, removes members, edits the guild and opens its goals.
     */
    public function administers(): bool
    {
        return self::Member !== $this;
    }

    /**
     * Whether this role may remove a member holding the other: the founder removes anyone but themselves, an
     * administrator removes members only.
     */
    public function canRemove(self $other): bool
    {
        return match ($this) {
            self::Founder => self::Founder !== $other,
            self::Administrator => self::Member === $other,
            self::Member => false,
        };
    }
}
