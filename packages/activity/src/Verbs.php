<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use Tilttek\Activity\Exception\UnknownVerb;

/**
 * The verbs an application declares, as patterns. No pattern lets every verb through; with patterns, a verb none of
 * them matches is refused before it is recorded.
 */
final readonly class Verbs
{
    /**
     * @var list<VerbPattern>
     */
    public array $patterns;

    public function __construct(VerbPattern ...$patterns)
    {
        $this->patterns = array_values($patterns);
    }

    /**
     * @param array<array-key, string> $patterns
     */
    public static function of(array $patterns): self
    {
        return new self(...array_map(VerbPattern::fromString(...), $patterns));
    }

    public function allows(Verb $verb): bool
    {
        if ([] === $this->patterns) {
            return true;
        }

        return array_any($this->patterns, static fn (VerbPattern $pattern): bool => $pattern->matches($verb));
    }

    /**
     * @throws UnknownVerb
     */
    public function check(Verb $verb): void
    {
        if (!$this->allows($verb)) {
            throw UnknownVerb::named($verb);
        }
    }
}
