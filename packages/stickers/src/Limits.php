<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Exception\StickerRefused;

/**
 * The limits of a site: how many stickers a surface holds, how many a holder places in all, and how far a sticker is
 * scaled and turned. Each has an absolute bound the site cannot go past.
 */
final readonly class Limits
{
    public const int MAX_PER_SURFACE = 100;

    public const int MAX_PER_HOLDER = 10_000;

    /**
     * @throws InvalidSticker when a limit is outside its absolute bound, or the smallest scale above the largest
     */
    public function __construct(
        public int $perSurface = 12,
        public int $perHolder = 500,
        public int $minScale = 50,
        public int $maxScale = 200,
        public int $maxRotation = 45,
    ) {
        if ($perSurface < 1 || $perSurface > self::MAX_PER_SURFACE) {
            throw InvalidSticker::because(\sprintf('A surface holds 1 to %d stickers; %d is outside.', self::MAX_PER_SURFACE, $perSurface));
        }

        if ($perHolder < 1 || $perHolder > self::MAX_PER_HOLDER) {
            throw InvalidSticker::because(\sprintf('A holder places 1 to %d stickers; %d is outside.', self::MAX_PER_HOLDER, $perHolder));
        }

        if ($minScale < Transform::MIN_SCALE || $maxScale > Transform::MAX_SCALE || $minScale > $maxScale) {
            throw InvalidSticker::because(\sprintf('The scales of a site are from %d to %d percent, the smallest first; %d to %d is not.', Transform::MIN_SCALE, Transform::MAX_SCALE, $minScale, $maxScale));
        }

        if ($maxRotation < 0 || $maxRotation > Transform::MAX_ROTATION) {
            throw InvalidSticker::because(\sprintf('The rotation of a site is 0 to %d degrees; %d is outside.', Transform::MAX_ROTATION, $maxRotation));
        }
    }

    /**
     * @throws StickerRefused when the scale or the rotation is outside the limits of the site
     */
    public function check(Transform $transform): Transform
    {
        if ($transform->scale < $this->minScale || $transform->scale > $this->maxScale) {
            throw StickerRefused::because(\sprintf('A sticker is scaled from %d to %d percent on this site; %d is outside.', $this->minScale, $this->maxScale, $transform->scale));
        }

        if (abs($transform->rotation) > $this->maxRotation) {
            throw StickerRefused::because(\sprintf('A sticker turns at most %d degrees either way on this site; %d is outside.', $this->maxRotation, $transform->rotation));
        }

        return $transform;
    }
}
