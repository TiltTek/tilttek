<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

/**
 * What a moderator says of a new placement: shown, or hidden for a reason the application names — `reported`,
 * `review`, `offensive` —, until moderation restores it.
 */
final readonly class Verdict
{
    private function __construct(
        public ?string $hideFor,
    ) {
    }

    public static function show(): self
    {
        return new self(null);
    }

    public static function hide(string $reason): self
    {
        return new self(Guard::code($reason, 'moderation reason'));
    }
}
