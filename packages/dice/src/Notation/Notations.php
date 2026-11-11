<?php

declare(strict_types=1);

namespace Tilttek\Dice\Notation;

/**
 * The notation of each language, found by locale — `de_AT` falls back on `de` —, and the canonical notation for a
 * language that declares none. The built-in ones are German, Danish, Norwegian (Bokmål and Nynorsk) and Swedish; an
 * application adds or replaces any.
 */
final readonly class Notations
{
    /**
     * @var array<string, Notation>
     */
    private array $notations;

    /**
     * @param array<string, Notation> $notations by locale or language, added to the built-in ones or replacing them
     */
    public function __construct(array $notations = [])
    {
        $terning = new Notation('T');
        $byLocale = ['de' => new Notation('W'), 'da' => $terning, 'nb' => $terning, 'nn' => $terning, 'no' => $terning, 'sv' => $terning];

        foreach ($notations as $locale => $notation) {
            $byLocale[$this->normalize($locale)] = $notation;
        }

        $this->notations = $byLocale;
    }

    public function for(?string $locale): Notation
    {
        if (null === $locale) {
            return new Notation();
        }

        $locale = $this->normalize($locale);

        return $this->notations[$locale] ?? $this->notations[explode('_', $locale)[0]] ?? new Notation();
    }

    private function normalize(string $locale): string
    {
        return str_replace('-', '_', strtolower($locale));
    }
}
