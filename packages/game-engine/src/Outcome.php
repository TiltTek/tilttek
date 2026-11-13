<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * How a game ended: the seats that won — none for a draw or a scene that simply ends —, why, in a word of the game's,
 * and the statistics the game reports at its end.
 */
final readonly class Outcome
{
    /**
     * @param list<string> $winners
     * @param string       $reason  the game's word for the ending, `target-reached`
     */
    public function __construct(
        public array $winners,
        public string $reason,
        public Statistics $statistics = new Statistics(),
    ) {
    }

    /**
     * @param list<string> $winners
     */
    public static function won(array $winners, string $reason, Statistics $statistics = new Statistics()): self
    {
        return new self($winners, $reason, $statistics);
    }

    public static function ended(string $reason, Statistics $statistics = new Statistics()): self
    {
        return new self([], $reason, $statistics);
    }

    public function hasWinners(): bool
    {
        return [] !== $this->winners;
    }

    /**
     * The outcome as plain values, which `fromArray()` reads back.
     *
     * @return array{winners: list<string>, reason: string, statistics: array{table: array<string, int>, seats: array<string, array<string, int>>}}
     */
    public function toArray(): array
    {
        return ['winners' => $this->winners, 'reason' => $this->reason, 'statistics' => ['table' => $this->statistics->table, 'seats' => $this->statistics->seats]];
    }

    /**
     * An outcome read back from the plain values `toArray()` wrote.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function fromArray(array $data): self
    {
        $of = 'an outcome';
        $statistics = Fields::map($data, 'statistics', $of);
        $seats = [];

        $bySeat = $statistics['seats'] ?? [];

        if (!\is_array($bySeat) || ([] !== $bySeat && array_is_list($bySeat))) {
            throw InvalidGame::because('The field "seats" of its statistics is not a mapping.');
        }

        foreach ($bySeat as $seat => $values) {
            // A key JSON wrote as a number is read back as an integer, which no seat identifier is.
            $seat = \is_string($seat) ? Name::identifier($seat, 'a seat') : throw InvalidGame::because(\sprintf('The statistics of a seat are under "%d", which is not a seat identifier.', $seat));
            $seats[$seat] = Fields::numbers([$seat => $values], $seat, 'its statistics');
        }

        return new self(Fields::texts($data, 'winners', $of), Fields::text($data, 'reason', $of), new Statistics(Fields::numbers($statistics, 'table', 'its statistics'), $seats));
    }
}
