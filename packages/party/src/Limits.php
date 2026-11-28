<?php

declare(strict_types=1);

namespace Tilttek\Party;

use Tilttek\Party\Exception\InvalidParty;

/**
 * The bounds of what parties hold, so that every read stays small: the members of a party — 12 by default, 1 to 100 —,
 * the parties a member belongs to — 10 by default, 1 to 100 —, the loot entries of a party — 500 by default, 1 to
 * 10,000 — and its notes — 200 by default, 1 to 5000.
 */
final readonly class Limits
{
    public const int DEFAULT_MEMBERS_PER_PARTY = 12;

    public const int MAX_MEMBERS_PER_PARTY = 100;

    public const int DEFAULT_PARTIES_PER_MEMBER = 10;

    public const int MAX_PARTIES_PER_MEMBER = 100;

    public const int DEFAULT_LOOT_PER_PARTY = 500;

    public const int MAX_LOOT_PER_PARTY = 10_000;

    public const int DEFAULT_NOTES_PER_PARTY = 200;

    public const int MAX_NOTES_PER_PARTY = 5000;

    public function __construct(
        public int $membersPerParty = self::DEFAULT_MEMBERS_PER_PARTY,
        public int $partiesPerMember = self::DEFAULT_PARTIES_PER_MEMBER,
        public int $lootPerParty = self::DEFAULT_LOOT_PER_PARTY,
        public int $notesPerParty = self::DEFAULT_NOTES_PER_PARTY,
    ) {
        $this->check('members per party', $membersPerParty, self::MAX_MEMBERS_PER_PARTY);
        $this->check('parties per member', $partiesPerMember, self::MAX_PARTIES_PER_MEMBER);
        $this->check('loot entries per party', $lootPerParty, self::MAX_LOOT_PER_PARTY);
        $this->check('notes per party', $notesPerParty, self::MAX_NOTES_PER_PARTY);
    }

    private function check(string $what, int $value, int $max): void
    {
        if ($value < 1 || $value > $max) {
            throw InvalidParty::because(\sprintf('The limit of %s is 1 to %d; it is %d.', $what, $max, $value));
        }
    }
}
