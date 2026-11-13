<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Benchmark;

use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Svg\SvgRenderer;

/**
 * What a coat of arms costs: one drawn from a seed, its blazon in French, and its SVG image — what a page showing new
 * arms does each time.
 */
final class HeraldryBench
{
    private readonly CoatGenerator $generator;

    private readonly Blazonry $blazonry;

    private readonly SvgRenderer $renderer;

    private readonly Coat $coat;

    private int $seed = 0;

    public function __construct()
    {
        $this->generator = new CoatGenerator();
        $this->blazonry = new Blazonry();
        $this->renderer = new SvgRenderer();
        $this->coat = $this->generator->generate(Seed::fromHex(str_repeat('2a', 32)));
    }

    public function benchGenerateFromASeed(): void
    {
        $this->generator->generate(Seed::fromHex(hash('sha256', (string) ++$this->seed)));
    }

    public function benchWriteTheFrenchBlazon(): void
    {
        $this->blazonry->write($this->coat, Language::French);
    }

    public function benchDrawTheSvg(): void
    {
        $this->renderer->render($this->coat);
    }
}
