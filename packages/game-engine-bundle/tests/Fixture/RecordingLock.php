<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Closure;
use Override;
use Tilttek\GameEngineBundle\Application\Contract\TableLock;

/**
 * A lock that remembers the tables it held, and whether the work ran while it held them.
 */
final class RecordingLock implements TableLock
{
    /** @var list<string> */
    public array $held = [];

    public ?string $holding = null;

    #[Override]
    public function hold(string $gameId, Closure $work): mixed
    {
        $this->held[] = $gameId;
        $this->holding = $gameId;

        try {
            return $work();
        } finally {
            $this->holding = null;
        }
    }
}
