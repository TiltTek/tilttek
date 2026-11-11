<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Tilttek\Progression\Progression;
use Tilttek\Progression\Standing;
use Tilttek\Progression\Streak\StreakStatus;
use Twig\Attribute\AsTwigFunction;

/**
 * The progression in the application's templates: `tilttek_progression_standing('member:44', 'player')` where a member
 * stands on a track, for the progress bar; `tilttek_progression_standings('member:44')` on every track;
 * `tilttek_progression_streak('member:44', 'daily-play')` a streak. A profile shows them only when the member chose to.
 */
final readonly class ProgressionExtension
{
    public function __construct(
        private Progression $progression,
        private string $domain,
    ) {
    }

    /**
     * The translation domain of the templates' texts.
     */
    #[AsTwigFunction('tilttek_progression_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    #[AsTwigFunction('tilttek_progression_standing')]
    public function standing(string $subject, string $track): Standing
    {
        return $this->progression->standing(ResourceReference::fromString($subject), $track);
    }

    /**
     * @return list<Standing>
     */
    #[AsTwigFunction('tilttek_progression_standings')]
    public function standings(string $subject): array
    {
        return $this->progression->standings(ResourceReference::fromString($subject));
    }

    #[AsTwigFunction('tilttek_progression_streak')]
    public function streak(string $member, string $streak): StreakStatus
    {
        return $this->progression->streak(ResourceReference::fromString($member), $streak);
    }
}
