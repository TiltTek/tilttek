<?php

declare(strict_types=1);

/*
 * The fuzz target of a placement request, run by `phil-dev fuzz`: any text reads as a request within the absolute
 * bounds, which the limits of a site accept or refuse with `StickerRefused`, or is refused with `InvalidSticker`, never
 * another exception.
 */

use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Exception\StickerRefused;
use Tilttek\Stickers\Limits;
use Tilttek\Stickers\PlacementRequest;
use Tilttek\Stickers\Transform;

require getenv('PHIL_DEV_AUTOLOAD');

$limits = new Limits();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($limits): void {
    try {
        $request = PlacementRequest::fromJson($input);
    } catch (InvalidSticker) {
        return;
    }

    $transform = $request->transform;

    if ($transform->x < 0 || $transform->x > Transform::MAX_POSITION || $transform->y < 0 || $transform->y > Transform::MAX_POSITION) {
        throw new LogicException('A request reads outside the layer.');
    }

    try {
        $limits->check($transform);
    } catch (StickerRefused) {
    }
});
$config->setMaxLen(1024);
