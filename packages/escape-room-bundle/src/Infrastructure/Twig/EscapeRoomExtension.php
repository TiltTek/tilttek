<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * The Twig functions of the templates: the name of a recipe of the kit, the translation domain, and a duration in
 * minutes and seconds.
 */
final readonly class EscapeRoomExtension
{
    public function __construct(
        private KitComponents $kit = new KitComponents(),
        private string $domain = 'tilttek_escape_room',
    ) {
    }

    #[AsTwigFunction('tilttek_escape_room_kit')]
    public function kit(string $component): string
    {
        return $this->kit->name($component);
    }

    #[AsTwigFunction('tilttek_escape_room_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * A duration as a clock reads it: `42:07`, or `1:02:07` past an hour.
     */
    #[AsTwigFunction('tilttek_escape_room_clock')]
    public function clock(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3_600);
        $minutes = intdiv($seconds % 3_600, 60);

        return $hours > 0
            ? \sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : \sprintf('%d:%02d', $minutes, $seconds % 60);
    }
}
