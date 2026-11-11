<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Showcase;
use Tilttek\Badges\Standing;
use Twig\Attribute\AsTwigFunction;

/**
 * The badges in the application's templates: `tilttek_badges_showcase('member:44')` is what anyone may see of a
 * member's badges, `tilttek_badges_overview('member:44')` where the member stands with each badge — for their own
 * page only, since it shows the badges they keep private.
 */
final readonly class BadgesExtension
{
    public function __construct(
        private Badges $badges,
        private string $domain,
    ) {
    }

    /**
     * The translation domain of the badges' names and descriptions.
     */
    #[AsTwigFunction('tilttek_badges_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    #[AsTwigFunction('tilttek_badges_showcase')]
    public function showcase(string $member): Showcase
    {
        return $this->badges->showcase(ResourceReference::fromString($member));
    }

    /**
     * @return list<Standing>
     */
    #[AsTwigFunction('tilttek_badges_overview')]
    public function overview(string $member): array
    {
        return $this->badges->overview(ResourceReference::fromString($member));
    }
}
