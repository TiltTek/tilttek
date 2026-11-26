<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * A table of a round: the participant who plays first, the one who plays second, or none for a bye or a rest, the pool
 * it belongs to, and the reasons of the pairing.
 */
final readonly class Pairing
{
    /**
     * @param list<PairingReason> $reasons
     *
     * @throws InvalidTournament when the table number is below 1, or when a participant meets themself
     */
    public function __construct(
        public int $table,
        public string $first,
        public ?string $second = null,
        public array $reasons = [],
        public ?string $pool = null,
        public bool $rest = false,
    ) {
        if ($table < 1) {
            throw InvalidTournament::because('A table is numbered from 1.');
        }

        if ($first === $second) {
            throw InvalidTournament::because('A participant never meets themself.');
        }
    }

    /**
     * The same pairing at another table.
     */
    public function at(int $table): self
    {
        return new self($table, $this->first, $this->second, $this->reasons, $this->pool, $this->rest);
    }

    /**
     * Whether the table has a single participant: a bye, which scores, or a rest, which does not.
     */
    public function isBye(): bool
    {
        return null === $this->second;
    }

    public function involves(string $participant): bool
    {
        return $participant === $this->first || $participant === $this->second;
    }

    /**
     * The opponent of a participant at this table; none for a bye, a rest, or a participant not at the table.
     */
    public function opponentOf(string $participant): ?string
    {
        return match ($participant) {
            $this->first => $this->second,
            $this->second => $this->first,
            default => null,
        };
    }

    /**
     * The reasons about one participant.
     *
     * @return list<PairingReason>
     */
    public function reasonsOf(string $participant): array
    {
        return array_values(array_filter($this->reasons, static fn (PairingReason $reason): bool => $participant === $reason->participant));
    }
}
