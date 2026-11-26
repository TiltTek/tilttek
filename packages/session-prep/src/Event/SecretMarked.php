<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A secret was revealed, or hidden again when `$revealed` is false.
 */
final readonly class SecretMarked implements PreparationEvent
{
    public function __construct(public string $preparation, public string $secret, public bool $revealed)
    {
    }
}
