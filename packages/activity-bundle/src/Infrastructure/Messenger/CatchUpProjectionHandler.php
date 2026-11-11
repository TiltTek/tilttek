<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Messenger;

use Tilttek\Activity\Exception\UnknownProjection;
use Tilttek\Activity\Projection\Checkpoints;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;

/**
 * Handles `CatchUpProjection`: hands the projection the entries after its checkpoint, unless its checkpoint is
 * already at the message's entry or past it. A projection the application no longer has is left alone.
 *
 * Two catch-ups of the same projection at the same time would apply the same entries twice: the transport is consumed
 * by one worker.
 */
final readonly class CatchUpProjectionHandler
{
    public function __construct(
        private Projector $projector,
        private Projections $projections,
        private Checkpoints $checkpoints,
    ) {
    }

    /**
     * @return int how many entries the projection applied
     */
    public function __invoke(CatchUpProjection $message): int
    {
        if ($this->checkpoints->position($message->projection) >= $message->position) {
            return 0;
        }

        try {
            $projection = $this->projections->get($message->projection);
        } catch (UnknownProjection) {
            return 0;
        }

        return $this->projector->catchUp($projection);
    }
}
