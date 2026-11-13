<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Key;
use Phil\ContentWarnings\WarningPreferences;
use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * A participant's lines and veils: per theme of the table, a line or a veil; a theme without either is open. Themes
 * are named by the keys of `phil/content-warnings` or by those the table adds. Immutable and bounded by
 * `MAX_THEMES`.
 */
final readonly class LinesAndVeils
{
    public const int MAX_THEMES = TableThemes::MAX_THEMES;

    /**
     * @param array<string, Boundary> $themes by theme key, in key order
     */
    private function __construct(
        private array $themes,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function with(string $theme, Boundary $boundary): self
    {
        return self::sorted([...$this->themes, self::key($theme) => $boundary]);
    }

    public function without(string $theme): self
    {
        $themes = $this->themes;
        unset($themes[$theme]);

        return new self($themes);
    }

    public function boundary(string $theme): ?Boundary
    {
        return $this->themes[$theme] ?? null;
    }

    /**
     * @return list<string> the themes behind a line, in key order
     */
    public function lines(): array
    {
        return $this->keysOf(Boundary::Line);
    }

    /**
     * @return list<string> the themes behind a veil, in key order
     */
    public function veils(): array
    {
        return $this->keysOf(Boundary::Veil);
    }

    /**
     * @return array<string, Boundary> by theme key, in key order
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
     * The preferences of `phil/content-warnings` the lines and veils stand for: a line hides a theme's warned content,
     * a veil folds it, the rest is shown.
     */
    public function preferences(): WarningPreferences
    {
        $preferences = WarningPreferences::defaults(Display::Show);

        foreach ($this->themes as $theme => $boundary) {
            $preferences = $preferences->withTheme($theme, $boundary->display());
        }

        return $preferences;
    }

    /**
     * @return array<string, string> the boundary of each theme, by theme key
     */
    public function toArray(): array
    {
        return array_map(static fn (Boundary $boundary): string => $boundary->value, $this->themes);
    }

    /**
     * @param array<array-key, mixed> $stored what `toArray()` returned
     */
    public static function fromArray(array $stored): self
    {
        $themes = [];

        foreach ($stored as $theme => $value) {
            $themes[self::key((string) $theme)] = Boundary::tryFrom(\is_string($value) ? $value : '') ?? throw InvalidAnswer::because(\sprintf('The theme "%s" has no boundary.', InvalidAnswer::excerpt((string) $theme)));
        }

        return self::sorted($themes);
    }

    /**
     * @return list<string>
     */
    private function keysOf(Boundary $boundary): array
    {
        return array_keys(array_filter($this->themes, static fn (Boundary $each): bool => $each === $boundary));
    }

    private static function key(string $theme): string
    {
        if (!Key::isValid($theme)) {
            throw InvalidAnswer::because(\sprintf('"%s" is not a theme key.', InvalidAnswer::excerpt($theme)));
        }

        return $theme;
    }

    /**
     * @param array<string, Boundary> $themes
     */
    private static function sorted(array $themes): self
    {
        if (\count($themes) > self::MAX_THEMES) {
            throw InvalidAnswer::because(\sprintf('Lines and veils name at most %d themes.', self::MAX_THEMES));
        }

        ksort($themes, \SORT_STRING);

        return new self($themes);
    }
}
