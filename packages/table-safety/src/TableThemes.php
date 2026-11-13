<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Key;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * The themes a table answers about: those of a catalogue of `phil/content-warnings`, the same list that warns of a
 * game's content, and those the table adds, each with the label its game master gave it. An added theme never shadows
 * a theme of the catalogue.
 */
final readonly class TableThemes
{
    /** How many themes a table may add. */
    public const int MAX_CUSTOM = 100;

    /** The longest label of an added theme, in characters. */
    public const int MAX_LABEL_LENGTH = 120;

    /** How many themes a table may know, the catalogue's and its own. */
    public const int MAX_THEMES = Catalogue::MAX_THEMES + self::MAX_CUSTOM;

    /**
     * @param array<array-key, string> $custom the label of each added theme, by key
     */
    public function __construct(
        public Catalogue $catalogue,
        public array $custom = [],
    ) {
        if (\count($custom) > self::MAX_CUSTOM) {
            throw InvalidAnswer::because(\sprintf('A table adds at most %d themes.', self::MAX_CUSTOM));
        }

        foreach ($custom as $key => $label) {
            $this->check((string) $key, $label, $catalogue);
        }
    }

    public function has(string $theme): bool
    {
        return $this->catalogue->has($theme) || isset($this->custom[$theme]);
    }

    /**
     * Whether the theme is one the table added.
     */
    public function isCustom(string $theme): bool
    {
        return isset($this->custom[$theme]);
    }

    /**
     * The label of a theme in a locale: the catalogue's, the one the table gave, or the key of a theme neither knows.
     *
     * @param list<string> $fallbacks
     */
    public function label(string $theme, string $locale, array $fallbacks = ['en']): string
    {
        return $this->custom[$theme] ?? $this->catalogue->theme($theme)?->label($locale, $fallbacks) ?? $theme;
    }

    /**
     * The keys among the given ones that the table does not know.
     *
     * @param iterable<string> $themes
     *
     * @return list<string>
     */
    public function unknown(iterable $themes): array
    {
        $unknown = [];

        foreach ($themes as $theme) {
            if (!$this->has($theme)) {
                $unknown[] = $theme;
            }
        }

        return $unknown;
    }

    /**
     * Refuses the lines and veils or the checklist when it names a theme the table does not know.
     *
     * @param array<string, mixed> $answers by theme key
     */
    public function assertKnown(array $answers): void
    {
        $unknown = $this->unknown(array_map(strval(...), array_keys($answers)));

        if ([] !== $unknown) {
            throw InvalidAnswer::because(\sprintf('The table does not know the theme "%s".', InvalidAnswer::excerpt($unknown[0])));
        }
    }

    private function check(string $key, string $label, Catalogue $catalogue): void
    {
        if (!Key::isValid($key)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a theme key.', InvalidAnswer::excerpt($key)));
        }

        if ($catalogue->has($key)) {
            throw InvalidAnswer::because(\sprintf('The theme "%s" is already in the catalogue.', $key));
        }

        $length = mb_strlen(trim($label));

        if (0 === $length || $length > self::MAX_LABEL_LENGTH) {
            throw InvalidAnswer::because(\sprintf('The label of the theme "%s" has 1 to %d characters.', $key, self::MAX_LABEL_LENGTH));
        }
    }
}
