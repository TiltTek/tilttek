<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

/**
 * A face of a custom die in words: its identifier, its label — what a screen reader says —, the sentence that
 * describes it, with its shape and its value, and how it is drawn.
 */
final readonly class FaceDescription
{
    public function __construct(
        public string $id,
        public string $label,
        public string $description,
        public ?int $value = null,
        public ?string $shape = null,
        public ?string $color = null,
        public ?string $image = null,
    ) {
    }
}
