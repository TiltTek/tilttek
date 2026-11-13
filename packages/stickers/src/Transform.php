<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Tilttek\Stickers\Exception\InvalidSticker;

/**
 * Where a sticker sits in the sticker layer of a surface and how: its centre in thousandths of the layer's width and
 * height, from the top left corner, its scale in percent of its natural size, and its rotation in whole degrees,
 * clockwise. The site's {@see Limits} narrow the scale and the rotation further.
 */
final readonly class Transform
{
    public const int MAX_POSITION = 1000;

    public const int MIN_SCALE = 10;

    public const int MAX_SCALE = 400;

    public const int MAX_ROTATION = 180;

    /**
     * @throws InvalidSticker when a value is out of its absolute bounds
     */
    public function __construct(
        public int $x,
        public int $y,
        public int $scale = 100,
        public int $rotation = 0,
    ) {
        if ($x < 0 || $x > self::MAX_POSITION || $y < 0 || $y > self::MAX_POSITION) {
            throw InvalidSticker::because(\sprintf('A sticker sits at 0 to %d thousandths of its layer; %d, %d is outside.', self::MAX_POSITION, $x, $y));
        }

        if ($scale < self::MIN_SCALE || $scale > self::MAX_SCALE) {
            throw InvalidSticker::because(\sprintf('A sticker is scaled from %d to %d percent; %d is outside.', self::MIN_SCALE, self::MAX_SCALE, $scale));
        }

        if ($rotation < -self::MAX_ROTATION || $rotation > self::MAX_ROTATION) {
            throw InvalidSticker::because(\sprintf('A sticker turns from -%d to %d degrees; %d is outside.', self::MAX_ROTATION, self::MAX_ROTATION, $rotation));
        }
    }

    /**
     * @return array{x: int, y: int, scale: int, rotation: int}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'scale' => $this->scale, 'rotation' => $this->rotation];
    }
}
