<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application;

/**
 * Who reads a table: a seat, with the preferences its player chose; the audience, which sees what the game shows a
 * null seat; or no one, refused.
 */
final readonly class Viewer
{
    /**
     * @param array<string, mixed> $preferences the values the player chose; the game's defaults for the others
     */
    private function __construct(
        public bool $allowed,
        public ?string $seat = null,
        public array $preferences = [],
    ) {
    }

    /**
     * @param array<string, mixed> $preferences
     */
    public static function seat(string $seat, array $preferences = []): self
    {
        return new self(true, $seat, $preferences);
    }

    /**
     * @param array<string, mixed> $preferences
     */
    public static function audience(array $preferences = []): self
    {
        return new self(true, null, $preferences);
    }

    public static function denied(): self
    {
        return new self(false);
    }
}
