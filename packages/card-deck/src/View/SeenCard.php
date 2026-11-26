<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\View;

use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Face;

/**
 * One card as a viewer sees it: its identifier and the face it shows when the viewer may see it, its back alone
 * otherwise. A hidden card carries neither identifier nor face.
 */
final readonly class SeenCard
{
    private function __construct(
        public ?CardId $id,
        public ?Face $face,
        public Face $back,
        public bool $faceUp,
    ) {
    }

    /**
     * @param bool $faceUp whether everyone sees it, or only the viewer, in their own hand
     */
    public static function visible(CardId $id, Face $face, Face $back, bool $faceUp): self
    {
        return new self($id, $face, $back, $faceUp);
    }

    public static function hidden(Face $back): self
    {
        return new self(null, null, $back, false);
    }

    public function isHidden(): bool
    {
        return !$this->id instanceof CardId;
    }

    /**
     * @return array{card: ?string, face: ?string, back: string, face_up: bool}
     */
    public function toArray(): array
    {
        return ['card' => $this->id?->value, 'face' => $this->face?->key, 'back' => $this->back->key, 'face_up' => $this->faceUp];
    }
}
