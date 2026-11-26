<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

use Tilttek\SessionPrep\SceneStatus;

/**
 * A scene was played, skipped, or planned again.
 */
final readonly class SceneMarked implements PreparationEvent
{
    public function __construct(public string $preparation, public string $scene, public SceneStatus $status)
    {
    }
}
