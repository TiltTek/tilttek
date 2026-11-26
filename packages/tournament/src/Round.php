<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use DateTimeImmutable;
use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * A round of a stage: its number, which orders the rounds, its tables, and the date it starts, kept apart from its
 * number so that a round moved to another day keeps its place.
 */
final readonly class Round
{
    /**
     * @param list<Pairing> $pairings by table
     *
     * @throws InvalidTournament when the number is below 1, a table number repeats, or a participant sits at two tables
     */
    public function __construct(
        public string $stage,
        public int $number,
        public array $pairings,
        public ?DateTimeImmutable $startsAt = null,
    ) {
        Identifier::check($stage, 'stage');

        if ($number < 1) {
            throw InvalidTournament::because('A round is numbered from 1.');
        }

        $tables = [];
        $seated = [];

        foreach ($pairings as $pairing) {
            if (\in_array($pairing->table, $tables, true)) {
                throw InvalidTournament::because(\sprintf('The table %d of a round is given twice.', $pairing->table));
            }

            $tables[] = $pairing->table;

            foreach ([$pairing->first, $pairing->second] as $participant) {
                if (null !== $participant && \in_array($participant, $seated, true)) {
                    throw InvalidTournament::because(\sprintf('The participant %s sits at two tables of a round.', $participant));
                }

                if (null !== $participant) {
                    $seated[] = $participant;
                }
            }
        }
    }

    /**
     * The key of a table of the round, the one results are kept under: stage, round and table.
     */
    public function key(int $table): string
    {
        return \sprintf('%s#%d#%d', $this->stage, $this->number, $table);
    }

    public function pairing(int $table): ?Pairing
    {
        foreach ($this->pairings as $pairing) {
            if ($table === $pairing->table) {
                return $pairing;
            }
        }

        return null;
    }

    public function pairingOf(string $participant): ?Pairing
    {
        foreach ($this->pairings as $pairing) {
            if ($pairing->involves($participant)) {
                return $pairing;
            }
        }

        return null;
    }

    public function at(?DateTimeImmutable $startsAt): self
    {
        return new self($this->stage, $this->number, $this->pairings, $startsAt);
    }
}
