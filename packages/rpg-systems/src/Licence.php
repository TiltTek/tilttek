<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

/**
 * The licences a record of the reference may be under, by their SPDX identifier when they have one. The list is
 * closed: material under any other licence is refused rather than carried without being read.
 */
enum Licence: string
{
    /** Creative Commons Attribution 4.0 International: reuse with attribution. */
    case CcBy40 = 'CC-BY-4.0';

    /** Creative Commons Attribution 3.0 Unported: reuse with attribution. */
    case CcBy30 = 'CC-BY-3.0';

    /** Creative Commons Attribution-ShareAlike 4.0 International: reuse with attribution, adaptations under the same licence. */
    case CcBySa40 = 'CC-BY-SA-4.0';

    /** Creative Commons Zero 1.0: dedicated to the public domain, no attribution required. */
    case Cc0 = 'CC0-1.0';

    /** The Open RPG Creative licence: reuse with its notice, the licensed material passed on under the same licence. */
    case Orc = 'ORC';

    /** The application's own material, which it shares under no licence. */
    case Owned = 'owned';

    public function title(): string
    {
        return match ($this) {
            self::CcBy40 => 'Creative Commons Attribution 4.0 International',
            self::CcBy30 => 'Creative Commons Attribution 3.0 Unported',
            self::CcBySa40 => 'Creative Commons Attribution-ShareAlike 4.0 International',
            self::Cc0 => 'Creative Commons Zero 1.0 Universal',
            self::Orc => 'ORC License',
            self::Owned => 'All rights reserved',
        };
    }

    /**
     * The page of the licence's text, or null for the application's own material.
     */
    public function url(): ?string
    {
        return match ($this) {
            self::CcBy40 => 'https://creativecommons.org/licenses/by/4.0/',
            self::CcBy30 => 'https://creativecommons.org/licenses/by/3.0/',
            self::CcBySa40 => 'https://creativecommons.org/licenses/by-sa/4.0/',
            self::Cc0 => 'https://creativecommons.org/publicdomain/zero/1.0/',
            self::Orc => 'https://paizo.com/orclicense',
            self::Owned => null,
        };
    }

    /**
     * Whether a work that shows the material must show its attribution.
     */
    public function requiresAttribution(): bool
    {
        return match ($this) {
            self::CcBy40, self::CcBy30, self::CcBySa40, self::Orc => true,
            self::Cc0, self::Owned => false,
        };
    }

    /**
     * Whether a work that adapts the material passes the material on under the same licence.
     */
    public function isReciprocal(): bool
    {
        return self::CcBySa40 === $this || self::Orc === $this;
    }
}
