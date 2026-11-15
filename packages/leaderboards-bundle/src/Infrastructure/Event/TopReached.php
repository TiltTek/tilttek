<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Appearance;

/**
 * A member reached the top, once per board, period and member: first when the period closed — the code of the period —,
 * in the lead of the period under way as their score changed, when the site celebrates on a score change, or promoted
 * into the highest tier when the week was settled — the code of the week. A listener congratulates them — a
 * notification, a badge — through the packages the application has; the screen the member opens next celebrates it
 * with `@TilttekLeaderboards/celebration.html.twig`.
 *
 * The event carries how the member chose to appear on the boards. A listener that tells the others — a feed, a message
 * to a channel — names the member only when they chose to appear by name, uses the pseudonym they chose, and says
 * nothing of who they are when they are hidden, which every member is until they choose.
 */
final readonly class TopReached
{
    public function __construct(
        public string $board,
        public string $period,
        public ResourceReference $member,
        public Summit $summit,
        public DateTimeImmutable $at,
        public Appearance $appearance = new Appearance(),
    ) {
    }
}
