<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Fixture;

use Override;
use Tilttek\GameNight\Contract\PlayJournal;
use Tilttek\GameNight\PlayedGame;

/**
 * A play log that keeps the games it receives, in order.
 */
final class Journal implements PlayJournal
{
    /** @var list<PlayedGame> */
    public array $plays = [];

    #[Override]
    public function record(PlayedGame $play): void
    {
        $this->plays[] = $play;
    }
}
