<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * The calendars in templates: `advent_calendar_kit(component)` names a component of the UX kit the screens draw with.
 */
final readonly class AdventCalendarExtension
{
    public function __construct(
        private KitComponents $kit = new KitComponents(),
    ) {
    }

    #[AsTwigFunction('advent_calendar_kit')]
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }
}
