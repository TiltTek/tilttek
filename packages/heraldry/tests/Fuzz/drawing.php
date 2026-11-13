<?php

declare(strict_types=1);

/*
 * The fuzz target of the generator and the drawing, run by `phil-dev fuzz`: any input, hashed into a seed and read for
 * a shape and a language, gives arms that keep the rule of tincture and an SVG image the sanitizer keeps whole.
 */

use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Svg\SvgRenderer;
use Tilttek\Heraldry\TinctureRule;

require getenv('PHIL_DEV_AUTOLOAD');

$generator = new CoatGenerator();
$renderer = new SvgRenderer();
$rule = new TinctureRule();
$sanitizer = new SvgSanitizer(new Policy()->withIdPrefix(null)->withAttributes('role', 'aria-labelledby'));
$shapes = ShieldShape::cases();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($generator, $renderer, $rule, $sanitizer, $shapes): void {
    $coat = $generator->generate(Seed::fromHex(hash('sha256', $input)));
    $byte = '' === $input ? 0 : ord($input[0]);
    $svg = $renderer->render($coat, $shapes[$byte % 4], 0 === $byte % 2 ? Language::English : Language::French, $input);

    if (!$rule->respects($coat)) {
        throw new LogicException('A generated coat breaks the rule of tincture.');
    }

    if (!$sanitizer->sanitize($svg)->isClean()) {
        throw new LogicException('The sanitizer removed part of a drawing: '.$svg);
    }
});
$config->setMaxLen(256);
