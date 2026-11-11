<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * What anyone may see of a member's badges, by the member's choice: the title beside their name, the showcase of their
 * profile in their order, and every badge they show. A badge they no longer hold is left out.
 */
final readonly class Showcase
{
    /**
     * @param list<Badge> $featured
     * @param list<Badge> $shown
     */
    public function __construct(
        public ?Badge $title = null,
        public array $featured = [],
        public array $shown = [],
    ) {
    }
}
