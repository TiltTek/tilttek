<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Fixture;

use Override;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenPublisher;

/**
 * A publisher that keeps the moves it is told of, as `game:move`.
 */
final class RecordingPublisher implements ScreenPublisher
{
    /** @var list<string> */
    public array $moves = [];

    #[Override]
    public function moved(string $game, string $move): void
    {
        $this->moves[] = $game.':'.$move;
    }
}
