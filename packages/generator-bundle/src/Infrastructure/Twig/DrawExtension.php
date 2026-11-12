<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\Twig;

use Tilttek\Generator\Draw;
use Tilttek\Generator\DrawResult;
use Tilttek\Generator\Generator;
use Twig\Attribute\AsTwigFunction;

/**
 * The generators in the application's templates: `tilttek_draw('name', 3, true)` draws three different results, and
 * `tilttek_draw('name', seed: 'x')` the same results each time.
 */
final readonly class DrawExtension
{
    public function __construct(private Generator $generator)
    {
    }

    #[AsTwigFunction('tilttek_draw')]
    public function draw(string $generator, int $count = 1, bool $unique = false, ?string $seed = null): DrawResult
    {
        return $this->generator->draw($generator, new Draw($count, $unique, $seed));
    }
}
