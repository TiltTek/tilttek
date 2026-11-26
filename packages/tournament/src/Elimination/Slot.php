<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Identifier;

/**
 * A place of a bracket, which a bracket made before its qualifiers are known shows as what will fill it: a participant,
 * the qualifier of a rank of an earlier stage or pool, the winner of an earlier match, or a bye.
 */
final readonly class Slot
{
    private function __construct(
        public SlotKind $kind,
        public ?string $participant = null,
        public ?string $from = null,
        public int $rank = 0,
        public int $match = 0,
    ) {
    }

    public static function participant(string $participant): self
    {
        return new self(SlotKind::Participant, Identifier::check($participant, 'participant'));
    }

    /**
     * The participant at a rank of an earlier stage, or of one of its pools, written `stage/pool`.
     */
    public static function qualifier(string $from, int $rank): self
    {
        if ($rank < 1) {
            throw InvalidTournament::because('The rank of a qualifier is 1 or more.');
        }

        return new self(SlotKind::Qualifier, from: $from, rank: $rank);
    }

    public static function winnerOf(int $match): self
    {
        if ($match < 1) {
            throw InvalidTournament::because('A match of a bracket is numbered from 1.');
        }

        return new self(SlotKind::WinnerOf, match: $match);
    }

    public static function bye(): self
    {
        return new self(SlotKind::Bye);
    }

    /**
     * What the place shows while it is not filled: the participant, `A#1` for the first of A, `W3` for the winner of
     * the match 3, or `bye`.
     */
    public function label(): string
    {
        return match ($this->kind) {
            SlotKind::Participant => (string) $this->participant,
            SlotKind::Qualifier => $this->from.'#'.$this->rank,
            SlotKind::WinnerOf => 'W'.$this->match,
            SlotKind::Bye => 'bye',
        };
    }
}
