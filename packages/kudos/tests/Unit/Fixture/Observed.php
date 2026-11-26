<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit\Fixture;

use Override;
use Tilttek\Kudos\Contract\KudosObserver;
use Tilttek\Kudos\KudosCancelled;
use Tilttek\Kudos\KudosGiven;

/**
 * An observer that keeps the events it was told of.
 */
final class Observed implements KudosObserver
{
    /**
     * @var list<KudosGiven>
     */
    public array $given = [];

    /**
     * @var list<KudosCancelled>
     */
    public array $cancelled = [];

    #[Override]
    public function given(KudosGiven $event): void
    {
        $this->given[] = $event;
    }

    #[Override]
    public function cancelled(KudosCancelled $event): void
    {
        $this->cancelled[] = $event;
    }
}
