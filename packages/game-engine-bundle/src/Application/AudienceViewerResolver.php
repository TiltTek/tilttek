<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application;

use Override;
use Tilttek\GameEngineBundle\Application\Contract\ViewerResolver;

/**
 * The viewer when the application names none: the audience of every table when `view.audience` is on, no one
 * otherwise. A seat's view needs the application's own resolver, which knows who holds the seat.
 */
final readonly class AudienceViewerResolver implements ViewerResolver
{
    public function __construct(
        private bool $audience = false,
    ) {
    }

    #[Override]
    public function resolve(string $gameId): Viewer
    {
        return $this->audience ? Viewer::audience() : Viewer::denied();
    }
}
