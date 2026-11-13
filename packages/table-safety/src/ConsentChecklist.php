<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Key;
use Phil\ContentWarnings\WarningPreferences;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A participant's consent checklist: per theme of the table, green, yellow or red; a theme left out is not answered.
 * Themes are named by the keys of `phil/content-warnings` or by those the table adds. Immutable and bounded by
 * `MAX_THEMES`.
 */
final readonly class ConsentChecklist
{
    public const int MAX_THEMES = TableThemes::MAX_THEMES;

    /**
     * @param array<string, ConsentLevel> $themes by theme key, in key order
     */
    private function __construct(
        private array $themes,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function with(string $theme, ConsentLevel $level): self
    {
        return self::sorted([...$this->themes, self::key($theme) => $level]);
    }

    public function without(string $theme): self
    {
        $themes = $this->themes;
        unset($themes[$theme]);

        return new self($themes);
    }

    public function level(string $theme): ?ConsentLevel
    {
        return $this->themes[$theme] ?? null;
    }

    /**
     * @return array<string, ConsentLevel> by theme key, in key order
     */
    public function all(): array
    {
        return $this->themes;
    }

    public function isEmpty(): bool
    {
        return [] === $this->themes;
    }

    /**
     * The preferences of `phil/content-warnings` the checklist stands for: red hides a theme's warned content, yellow
     * folds it, green and the themes not answered show it.
     */
    public function preferences(): WarningPreferences
    {
        $preferences = WarningPreferences::defaults(Display::Show);

        foreach ($this->themes as $theme => $level) {
            $preferences = $preferences->withTheme($theme, $level->display());
        }

        return $preferences;
    }

    /**
     * @return array<string, string> the level of each theme, by theme key
     */
    public function toArray(): array
    {
        return array_map(static fn (ConsentLevel $level): string => $level->value, $this->themes);
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $themes = [];

        foreach ($stored as $theme => $value) {
            $themes[self::key((string) $theme)] = ConsentLevel::tryFrom(\is_string($value) ? $value : '') ?? throw InvalidAnswer::because(\sprintf('The theme "%s" has no consent level.', InvalidAnswer::excerpt((string) $theme)));
        }

        return self::sorted($themes);
    }

    private static function key(string $theme): string
    {
        if (!Key::isValid($theme)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a theme key.', InvalidAnswer::excerpt($theme)));
        }

        return $theme;
    }

    /**
     * @param array<string, ConsentLevel> $themes
     */
    private static function sorted(array $themes): self
    {
        if (\count($themes) > self::MAX_THEMES) {
            throw InvalidAnswer::because(\sprintf('A consent checklist names at most %d themes.', self::MAX_THEMES));
        }

        ksort($themes, \SORT_STRING);

        return new self($themes);
    }
}
