<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use Tilttek\Guilds\Exception\InvalidGuild;

/**
 * The bounds of what members and guilds hold, so that every read stays small: the guilds a member belongs to or asked
 * to join — 10 by default, 1 to 100 —, the requests a guild keeps waiting — 100 by default, 1 to 1000 —, and the
 * goals a guild keeps open — 10 by default, 1 to 100.
 */
final readonly class Limits
{
    public const int DEFAULT_GUILDS_PER_MEMBER = 10;

    public const int MAX_GUILDS_PER_MEMBER = 100;

    public const int DEFAULT_REQUESTS_PER_GUILD = 100;

    public const int MAX_REQUESTS_PER_GUILD = 1000;

    public const int DEFAULT_OPEN_GOALS = 10;

    public const int MAX_OPEN_GOALS = 100;

    public function __construct(
        public int $guildsPerMember = self::DEFAULT_GUILDS_PER_MEMBER,
        public int $requestsPerGuild = self::DEFAULT_REQUESTS_PER_GUILD,
        public int $openGoals = self::DEFAULT_OPEN_GOALS,
    ) {
        $this->check('guilds per member', $guildsPerMember, self::MAX_GUILDS_PER_MEMBER);
        $this->check('waiting requests per guild', $requestsPerGuild, self::MAX_REQUESTS_PER_GUILD);
        $this->check('open goals per guild', $openGoals, self::MAX_OPEN_GOALS);
    }

    private function check(string $what, int $value, int $max): void
    {
        if ($value < 1 || $value > $max) {
            throw InvalidGuild::because(\sprintf('The limit of %s is 1 to %d; it is %d.', $what, $max, $value));
        }
    }
}
