<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

/**
 * A die the interface offers: its name — `d6`, or the name of a custom die —, how an expression writes it — `d6`,
 * `d{coin}` —, its label, and the description of each face of a custom die.
 */
final readonly class DieChoice
{
    /**
     * @param list<FaceDescription> $faces
     */
    public function __construct(
        public string $name,
        public string $notation,
        public string $label,
        public array $faces = [],
    ) {
    }
}
