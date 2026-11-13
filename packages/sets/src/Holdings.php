<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Tilttek\Sets\Exception\InvalidSet;

/**
 * What a holder owns, as the application reads it from its own store: each member's stable identifier with the
 * highest level the holder owns it at — 1 for a plain copy, 2 and more for a member redone at a higher level. A member
 * the holder does not own is absent.
 */
final readonly class Holdings
{
    public const int MAX_ENTRIES = 100_000;

    /**
     * @param array<string, int> $levels
     */
    private function __construct(
        private array $levels,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * Holdings at level 1 for each identifier given; an identifier given twice is owned once.
     *
     * @param iterable<string> $members
     *
     * @throws InvalidSet when an identifier is refused, or when there are more than {@see self::MAX_ENTRIES}
     */
    public static function of(iterable $members): self
    {
        $levels = [];

        foreach ($members as $member) {
            $levels[Identifiers::member($member)] = 1;
            self::bound($levels);
        }

        return new self($levels);
    }

    /**
     * Holdings at the level given for each identifier; a level of 0 means the member is not owned.
     *
     * @param array<array-key, int> $levels
     *
     * @throws InvalidSet when an identifier or a level is refused, or when there are more than {@see self::MAX_ENTRIES}
     */
    public static function withLevels(array $levels): self
    {
        $owned = [];

        foreach ($levels as $member => $level) {
            $member = Identifiers::member((string) $member);

            if ($level < 0 || $level > Tier::MAX_LEVEL) {
                throw InvalidSet::because(\sprintf('A member is owned at a level of 0 to %d; "%s" is at %d.', Tier::MAX_LEVEL, $member, $level));
            }

            if (0 !== $level) {
                $owned[$member] = $level;
                self::bound($owned);
            }
        }

        return new self($owned);
    }

    /**
     * The highest level the holder owns the member at, 0 when they do not own it.
     */
    public function levelOf(string $member): int
    {
        return $this->levels[$member] ?? 0;
    }

    /**
     * How many distinct members the holder owns.
     */
    public function count(): int
    {
        return \count($this->levels);
    }

    /**
     * @param array<string, int> $levels
     */
    private static function bound(array $levels): void
    {
        if (\count($levels) > self::MAX_ENTRIES) {
            throw InvalidSet::because(\sprintf('Holdings hold at most %d members.', self::MAX_ENTRIES));
        }
    }
}
