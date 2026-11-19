<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use DateTimeImmutable;
use Tilttek\Raffle\Beacon\BeaconChain;

/**
 * What the organiser publishes when the entries close, before anyone can know the winners: the number and the hash of
 * the entrants, and the source of the draw — the beacon round to come, with the time it is published, or the
 * commitment to the organiser's seed, published since the raffle opened.
 */
final readonly class Announcement
{
    public function __construct(
        public string $raffle,
        public Stake $stake,
        public SeedSource $source,
        public int $winners,
        public int $alternates,
        public int $entrants,
        public string $entrantsHash,
        public DateTimeImmutable $sealedAt,
        public ?string $commitment,
        public ?BeaconChain $chain,
        public ?int $round,
    ) {
    }

    /**
     * The time the beacon round is published, after which the raffle may be drawn; null for commit-reveal.
     */
    public function drawableAt(): ?DateTimeImmutable
    {
        return !$this->chain instanceof BeaconChain || null === $this->round ? null : $this->chain->timeOf($this->round);
    }

    /**
     * @return array{raffle: string, stake: string, source: string, winners: int, alternates: int, entrants: int, entrants_hash: string, sealed_at: string, commitment: ?string, chain: ?string, round: ?int, drawable_at: ?string}
     */
    public function toArray(): array
    {
        $drawable = $this->drawableAt();

        return [
            'raffle' => $this->raffle,
            'stake' => $this->stake->value,
            'source' => $this->source->value,
            'winners' => $this->winners,
            'alternates' => $this->alternates,
            'entrants' => $this->entrants,
            'entrants_hash' => $this->entrantsHash,
            'sealed_at' => Instant::write($this->sealedAt),
            'commitment' => $this->commitment,
            'chain' => $this->chain?->hash,
            'round' => $this->round,
            'drawable_at' => $drawable instanceof DateTimeImmutable ? Instant::write($drawable) : null,
        ];
    }
}
