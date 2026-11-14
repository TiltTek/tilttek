<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

/**
 * The skins the library ships, open to every member: seven readable looks and the high-contrast one. `ivory` is the
 * default a site starts from. A site adds its own skins beside them, or offers others in their place.
 */
final class BaseSkins
{
    public const string DEFAULT = 'ivory';

    public const string HIGH_CONTRAST = 'high-contrast';

    private const array DEFINITIONS = [
        ['key' => 'ivory', 'label' => 'Ivory', 'face' => '#f6f1e3', 'ink' => '#1f1a14', 'material' => 'solid'],
        ['key' => 'obsidian', 'label' => 'Obsidian', 'face' => '#1b1b1f', 'ink' => '#f5f5f5', 'material' => 'stone'],
        ['key' => 'crimson', 'label' => 'Crimson', 'face' => '#9b1b30', 'ink' => '#ffffff', 'material' => 'pearl'],
        ['key' => 'forest', 'label' => 'Forest', 'face' => '#1e5631', 'ink' => '#ffffff', 'material' => 'marble'],
        ['key' => 'ocean', 'label' => 'Ocean', 'face' => '#0b4f6c', 'ink' => '#ffffff', 'material' => 'translucent'],
        ['key' => 'amethyst', 'label' => 'Amethyst', 'face' => '#5b2a86', 'ink' => '#ffffff', 'material' => 'gem'],
        ['key' => 'gold', 'label' => 'Gold', 'face' => '#d4a017', 'ink' => '#1a1a1a', 'material' => 'metal'],
        ['key' => 'high-contrast', 'label' => 'High contrast', 'face' => '#ffffff', 'ink' => '#000000', 'material' => 'solid'],
    ];

    private function __construct()
    {
    }

    /**
     * @return list<Skin>
     */
    public static function all(): array
    {
        return SkinDefinitions::fromArray(self::DEFINITIONS);
    }

    /**
     * Black numbers on white faces, solid and without texture: 21:1, the look a die takes for a viewer who asks for
     * high contrast when its own look does not reach 7:1.
     */
    public static function highContrast(): Skin
    {
        return new Skin(self::HIGH_CONTRAST, 'High contrast', new SkinLook(Color::white(), Color::black()));
    }
}
