<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * One reason of a pairing: its kind, the participant it is about, and the other participant and the round it refers
 * to, when it has them.
 */
final readonly class PairingReason
{
    public function __construct(
        public ReasonKind $kind,
        public string $participant,
        public ?string $other = null,
        public ?int $round = null,
    ) {
    }
}
