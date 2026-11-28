<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

use Tilttek\GameEngine\Outcome;

/**
 * The end of a game as one seat lives it: its tone, the game's word for the ending, and the seats that won.
 */
final readonly class Ending
{
    /**
     * @param list<string> $winners
     */
    public function __construct(
        public Tone $tone,
        public string $reason,
        public array $winners,
    ) {
    }

    /**
     * The ending of a seat — or of the audience, with no seat —, from the outcome of its table.
     */
    public static function of(Outcome $outcome, ?string $seat): self
    {
        $tone = match (true) {
            !\in_array($seat, $outcome->winners, true) => Tone::GoodGame,
            1 === \count($outcome->winners) => Tone::Victory,
            default => Tone::Tie,
        };

        return new self($tone, $outcome->reason, $outcome->winners);
    }
}
