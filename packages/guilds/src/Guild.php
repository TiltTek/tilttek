<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Guilds\Exception\InvalidGuild;

/**
 * A guild: a lasting community of members, named by the reference the application gives it — `guild:44` —, with its
 * name, its admission, its capacity and its metadata.
 *
 * The name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces. The capacity, 2 to
 * 1000 members, is the most members it holds: a full guild refuses whoever joins, and lowering the capacity below the
 * members it holds is refused, never solved by removing anyone.
 */
final readonly class Guild
{
    public const int MAX_NAME_LENGTH = 100;

    public const int MIN_CAPACITY = 2;

    public const int MAX_CAPACITY = 1000;

    public const int DEFAULT_CAPACITY = 50;

    public function __construct(
        public ResourceReference $reference,
        public string $name,
        public Admission $admission,
        public int $capacity,
        public Metadata $metadata,
        public DateTimeImmutable $foundedAt,
    ) {
        if (1 !== preg_match('/^\P{Cc}{1,100}\z/u', $name) || trim($name) !== $name) {
            throw InvalidGuild::because(\sprintf('A guild name is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', self::MAX_NAME_LENGTH));
        }

        if ($capacity < self::MIN_CAPACITY || $capacity > self::MAX_CAPACITY) {
            throw InvalidGuild::because(\sprintf('A guild holds %d to %d members; not %d.', self::MIN_CAPACITY, self::MAX_CAPACITY, $capacity));
        }
    }

    /**
     * The guild with the values given changed, the others kept.
     */
    public function with(?string $name = null, ?Admission $admission = null, ?int $capacity = null, ?Metadata $metadata = null): self
    {
        return new self($this->reference, $name ?? $this->name, $admission ?? $this->admission, $capacity ?? $this->capacity, $metadata ?? $this->metadata, $this->foundedAt);
    }
}
