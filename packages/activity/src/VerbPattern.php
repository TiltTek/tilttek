<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * A verb, or every verb under a prefix: `game.played` matches that verb alone, `game.*` matches `game.played` and
 * `game.won.flawless` but not `game` nor `gamebook.read`, and `*` matches every verb.
 */
final readonly class VerbPattern
{
    private function __construct(
        public string $value,
        private ?Verb $prefix,
        private bool $wildcard,
    ) {
    }

    public static function fromString(string $value): self
    {
        if ('*' === $value) {
            return new self($value, null, true);
        }

        if (str_ends_with($value, '.*')) {
            return new self($value, Verb::fromString(substr($value, 0, -2)), true);
        }

        return new self($value, Verb::fromString($value), false);
    }

    public function matches(Verb $verb): bool
    {
        if (!$this->prefix instanceof Verb) {
            return true;
        }

        if (!$this->wildcard) {
            return $verb->equals($this->prefix);
        }

        return str_starts_with($verb->value, $this->prefix->value.'.');
    }

    /**
     * The verb the pattern names exactly, or null when it is a prefix or `*`.
     */
    public function exact(): ?Verb
    {
        return $this->wildcard ? null : $this->prefix;
    }

    /**
     * The prefix a matching verb starts with, dot included — `game.` for `game.*` —, an empty string for `*`, or null
     * when the pattern names one verb.
     */
    public function prefix(): ?string
    {
        if (!$this->wildcard) {
            return null;
        }

        return $this->prefix instanceof Verb ? $this->prefix->value.'.' : '';
    }
}
