<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Guilds\Exception\InvalidGuild;

/**
 * A goal a guild works towards together: a target of points its members' contributions add up to, before a deadline
 * or not. Points are counters of the application — games played, pages read, hours painted —, never money.
 *
 * The key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; the name 1 to 100 characters of UTF-8 without
 * control characters nor surrounding spaces; the target 1 to 1,000,000,000 points. A goal is over once reached,
 * closed, or past its deadline.
 */
final readonly class Goal
{
    public const int MAX_TARGET = 1_000_000_000;

    public const int MAX_KEY_LENGTH = 100;

    public function __construct(
        public string $key,
        public ResourceReference $guild,
        public string $name,
        public int $target,
        public DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $deadline = null,
        public ?DateTimeImmutable $reachedAt = null,
        public ?DateTimeImmutable $closedAt = null,
    ) {
        self::checkKey($key, 'goal');

        if (1 !== preg_match('/^\P{Cc}{1,100}\z/u', $name) || trim($name) !== $name) {
            throw InvalidGuild::because('A goal name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.');
        }

        if ($target < 1 || $target > self::MAX_TARGET) {
            throw InvalidGuild::because(\sprintf('A goal targets 1 to %d points; not %d.', self::MAX_TARGET, $target));
        }

        if ($deadline instanceof DateTimeImmutable && $deadline <= $openedAt) {
            throw InvalidGuild::because("A goal's deadline comes after its opening.");
        }
    }

    /**
     * Checks a goal's or a contribution's key: 1 to 100 ASCII letters, digits, "_", ".", ":" or "-".
     */
    public static function checkKey(string $key, string $what): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9_.:-]{1,100}$/D', $key)) {
            throw InvalidGuild::because(\sprintf('A %s key is 1 to %d ASCII letters, digits, "_", ".", ":" or "-"; "%s" is not.', $what, self::MAX_KEY_LENGTH, $key));
        }
    }

    /**
     * Whether the goal no longer takes contributions at this moment: reached, closed, or at or past its deadline.
     */
    public function isOver(DateTimeImmutable $at): bool
    {
        return $this->reachedAt instanceof DateTimeImmutable || $this->closedAt instanceof DateTimeImmutable || ($this->deadline instanceof DateTimeImmutable && $at >= $this->deadline);
    }

    public function reached(DateTimeImmutable $at): self
    {
        return new self($this->key, $this->guild, $this->name, $this->target, $this->openedAt, $this->deadline, $at, $this->closedAt);
    }

    public function closed(DateTimeImmutable $at): self
    {
        return new self($this->key, $this->guild, $this->name, $this->target, $this->openedAt, $this->deadline, $this->reachedAt, $at);
    }
}
