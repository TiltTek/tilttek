<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * The texts of a system in one more language, with the source of that translation: a translation is a work of its
 * own, whose licence may ask for its attribution too. `GameSystem::localised()` lays it over a system.
 */
final readonly class Localisation
{
    public string $locale;

    /**
     * @param string                                                                                   $locale       the language of the texts, `fr`
     * @param Provenance                                                                               $source       who translated, under which licence
     * @param ?string                                                                                  $systemName   the system's name in that language
     * @param array<string, array{name?: string, abbreviation?: string, description?: string}>         $attributes   the texts of attributes, by key
     * @param array<string, string>                                                                    $dice         the names of dice expressions, by key
     * @param array<string, array{name?: string, description?: string}>                                $terms        the texts of terms, by key
     * @param array<string, array{name?: string, description?: string}>                                $variants     the texts of rule variants, by key
     * @param array<string, array{name?: string, description?: string, bands?: array<string, string>}> $difficulties the texts of
     *                                                                                                               difficulty numbers, the names of their bands by key, by key
     * @param array<string, array{name?: string, description?: string}>                                $partyRoles   the texts of party roles, by key
     *
     * @throws InvalidRecord
     */
    public function __construct(
        string $locale,
        public Provenance $source,
        public ?string $systemName = null,
        public array $attributes = [],
        public array $dice = [],
        public array $terms = [],
        public array $variants = [],
        public array $difficulties = [],
        public array $partyRoles = [],
    ) {
        $this->locale = Text::locale($locale);
    }
}
