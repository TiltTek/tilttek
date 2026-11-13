<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application\Contract;

use Tilttek\GameEngineBundle\Application\Viewer;

/**
 * Says who reads a table through the endpoint of the views: the seat of the current player and their preferences, the
 * audience, or no one. The application knows its members and which seat each holds; the bundle never does.
 */
interface ViewerResolver
{
    public function resolve(string $gameId): Viewer;
}
