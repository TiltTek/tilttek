<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Policy;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Contract\SurfacePolicy;

/**
 * The owners of the surfaces in memory, for tests and for an application that knows them when it builds the service:
 * a holder places stickers on the surfaces assigned to them, and on no other.
 */
final class InMemorySurfacePolicy implements SurfacePolicy
{
    /** @var array<string, string> */
    private array $owners = [];

    public function assign(ResourceReference $surface, ResourceReference $holder): void
    {
        $this->owners[$surface->toString()] = $holder->toString();
    }

    #[Override]
    public function allows(ResourceReference $holder, ResourceReference $surface): bool
    {
        return ($this->owners[$surface->toString()] ?? null) === $holder->toString();
    }
}
