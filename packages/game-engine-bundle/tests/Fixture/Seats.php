<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Fixture;

use Override;
use Tilttek\GameEngineBundle\Application\Contract\ViewerResolver;
use Tilttek\GameEngineBundle\Application\Viewer;

/**
 * A resolver that gives the viewer it was told, and remembers the tables it was asked about.
 */
final class Seats implements ViewerResolver
{
    /** @var list<string> */
    public array $asked = [];

    public function __construct(public Viewer $viewer)
    {
    }

    #[Override]
    public function resolve(string $gameId): Viewer
    {
        $this->asked[] = $gameId;

        return $this->viewer;
    }
}
