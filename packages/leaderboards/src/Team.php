<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * A team the application gives — a house, a guild, a table —: its name and its members, whose scores on a board add
 * up to the team's. Tilttek keeps no team: the application says who is in it each time.
 */
final readonly class Team
{
    public function __construct(
        public string $name,
        public Circle $members,
    ) {
        Names::check($name, 'team');
    }
}
