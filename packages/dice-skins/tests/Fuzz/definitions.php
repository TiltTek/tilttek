<?php

declare(strict_types=1);

/*
 * The fuzz target of the skin definitions, run by `phil-dev fuzz`: any text gives a list of skins, each readable and
 * written back as it was read, or is refused with `InvalidSkin`, never another exception.
 */
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinDefinitions;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $skins = SkinDefinitions::fromJson($input);
    } catch (InvalidSkin) {
        return;
    }

    foreach ($skins as $skin) {
        foreach ([$skin->look, ...array_values($skin->types)] as $look) {
            if (!$look->reaches(Color::AA)) {
                throw new LogicException(sprintf('The skin "%s" was read with numbers under 4.5:1.', $skin->key));
            }
        }
    }

    $written = array_map(static fn (Skin $skin): array => $skin->toArray(), $skins);

    if (array_map(static fn (Skin $skin): array => $skin->toArray(), SkinDefinitions::fromArray($written)) !== $written) {
        throw new LogicException('The skins written do not read back the same.');
    }
});
$config->setMaxLen(4096);
