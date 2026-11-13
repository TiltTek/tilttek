<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;

/**
 * A roll the game master made out of sight: its opening, sealed until it is revealed, who rolled it and when. The game
 * master reads its result at once; the table sees only its commitment until the reveal.
 */
final readonly class HiddenRoll
{
    public DateTimeImmutable $rolledAt;

    public function __construct(
        public RollOpening $opening,
        public ?string $roller,
        DateTimeImmutable $rolledAt,
    ) {
        $this->rolledAt = $rolledAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * A hidden roll as `toArray()` wrote it, for a store.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidSecretRoll when the data does not read as a hidden roll
     */
    public static function fromArray(array $data): self
    {
        $opening = $data['opening'] ?? null;
        $roller = $data['roller'] ?? null;
        $rolledAt = $data['rolled_at'] ?? null;

        if (!\is_array($opening) || (null !== $roller && !\is_string($roller)) || !\is_string($rolledAt)) {
            throw InvalidSecretRoll::because('A hidden roll holds an opening, a roller or null, and the time it was rolled.');
        }

        return new self(RollOpening::fromArray($opening), Encoding::text($roller, 'roller', Encoding::MAX_ROLLER), Instant::read($rolledAt));
    }

    public function roll(): string
    {
        return $this->opening->roll;
    }

    public function scene(): string
    {
        return $this->opening->scene;
    }

    public function commitment(): RollCommitment
    {
        return $this->opening->commitment();
    }

    /**
     * @return array{opening: array{roll: string, scene: string, position: int, expression: string, rounding: string, seed: string, result: list<array<array-key, mixed>>, context: ?string}, roller: ?string, rolled_at: string}
     */
    public function toArray(): array
    {
        return ['opening' => $this->opening->toArray(), 'roller' => $this->roller, 'rolled_at' => Instant::write($this->rolledAt)];
    }
}
