<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\View;

use DateTimeImmutable;
use Tilttek\PuzzleBuilder\Instant;
use Tilttek\PuzzleBuilder\PuzzleStatus;

/**
 * What the players see of one puzzle: never its answer nor anything that checks it. A locked puzzle shows its
 * identifier and status only.
 */
final readonly class PuzzleView
{
    /**
     * @param ?string      $title the title, null while the puzzle is locked
     * @param list<string> $hints the hints revealed, tier after tier
     * @param int          $left  the tiers still hidden
     */
    public function __construct(
        public string $id,
        public PuzzleStatus $status,
        public ?string $title,
        public array $hints,
        public int $left,
        public int $guesses,
        public ?DateTimeImmutable $solvedAt,
        public bool $skipped,
    ) {
    }

    /**
     * @return array{id: string, status: string, title: ?string, hints: list<string>, hints_left: int, guesses: int, solved_at: ?string, skipped: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'title' => $this->title,
            'hints' => $this->hints,
            'hints_left' => $this->left,
            'guesses' => $this->guesses,
            'solved_at' => $this->solvedAt instanceof DateTimeImmutable ? Instant::write($this->solvedAt) : null,
            'skipped' => $this->skipped,
        ];
    }
}
