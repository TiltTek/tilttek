<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * The ways a game is won, in the order they are checked: the first matched names the win.
 */
final readonly class WinPatterns
{
    public const int MOST_PATTERNS = 16;

    /** @var list<WinPattern> */
    public array $patterns;

    public function __construct(WinPattern ...$patterns)
    {
        $count = \count($patterns);

        if ($count < 1 || $count > self::MOST_PATTERNS) {
            throw InvalidBingo::because(\sprintf('A game is won in 1 to %d ways; this one is won in %d.', self::MOST_PATTERNS, $count));
        }

        $names = [];

        foreach ($patterns as $pattern) {
            if (isset($names[$pattern->name])) {
                throw InvalidBingo::because(\sprintf('Two win patterns are named %s.', $pattern->name));
            }

            $names[$pattern->name] = $pattern;
        }

        $this->patterns = array_values($patterns);
    }

    /** A row, a column or a diagonal: the classic line. */
    public static function line(Grid $grid): self
    {
        return new self(WinPattern::row($grid), WinPattern::column($grid), WinPattern::diagonal($grid));
    }

    /**
     * The first pattern the ticked cells match, or null.
     *
     * @param array<int, mixed> $ticked the cells ticked, as keys
     */
    public function matched(array $ticked): ?WinPattern
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matchedBy($ticked)) {
                return $pattern;
            }
        }

        return null;
    }
}
