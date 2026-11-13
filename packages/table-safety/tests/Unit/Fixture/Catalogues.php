<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Fixture;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Category;
use Phil\ContentWarnings\LocalizedText;
use Phil\ContentWarnings\Theme;

/**
 * A small catalogue of themes: gore and torture in violence, spiders in phobias.
 */
final class Catalogues
{
    private function __construct()
    {
    }

    public static function small(): Catalogue
    {
        return new Catalogue(
            [
                new Category('violence', LocalizedText::of(['en' => 'Violence', 'fr' => 'Violence'])),
                new Category('phobias', LocalizedText::of(['en' => 'Phobias', 'fr' => 'Phobies'])),
            ],
            [
                new Theme('gore', 'violence', LocalizedText::of(['en' => 'Gore', 'fr' => 'Gore'])),
                new Theme('torture', 'violence', LocalizedText::of(['en' => 'Torture', 'fr' => 'Torture'])),
                new Theme('spiders', 'phobias', LocalizedText::of(['en' => 'Spiders', 'fr' => 'Araignées'])),
            ],
        );
    }
}
