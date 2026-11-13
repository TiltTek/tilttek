<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Functional\App;

use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Tilttek\GameEngineBundle\Application\Contract\ViewerResolver;
use Tilttek\GameEngineBundle\Application\Viewer;

/**
 * The seat of the current request, as its `X-Seat` header names it — what an application reads from the member who is
 * signed in —; the audience without one.
 */
final readonly class SeatHeader implements ViewerResolver
{
    public function __construct(private RequestStack $requests)
    {
    }

    #[Override]
    public function resolve(string $gameId): Viewer
    {
        $seat = $this->requests->getCurrentRequest()?->headers->get('X-Seat');

        return null === $seat ? Viewer::audience() : Viewer::seat($seat, ['leader-first' => true]);
    }
}
