<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;

/**
 * The first time a holder completed a set, or reached one of its tiers: written once, with the version of the set it
 * was earned under and its moment in UTC, so that it is told once and a later version of the set never takes it back.
 */
final readonly class Completion
{
    public DateTimeImmutable $at;

    /**
     * @param ?string $tier the tier reached, or null for the completion of the set itself
     */
    public function __construct(
        public string $set,
        public ResourceReference $holder,
        public ?string $tier,
        public int $version,
        DateTimeImmutable $at,
    ) {
        Identifiers::code($set, 'set');
        Identifiers::holder($holder);

        if (null !== $tier) {
            Identifiers::code($tier, 'tier');
        }

        $this->at = $at->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Whether this is the completion of the set itself, not of one of its tiers.
     */
    public function isOfSet(): bool
    {
        return null === $this->tier;
    }

    /**
     * What a store keeps one completion of: the set, the tier or none, and the holder.
     */
    public function key(): string
    {
        return $this->set."\0".($this->tier ?? '')."\0".$this->holder->toString();
    }

    /**
     * @return array{set: string, holder: string, tier: ?string, version: int, at: string}
     */
    public function toArray(): array
    {
        return [
            'set' => $this->set,
            'holder' => $this->holder->toString(),
            'tier' => $this->tier,
            'version' => $this->version,
            'at' => $this->at->format('Y-m-d\TH:i:s.up'),
        ];
    }
}
