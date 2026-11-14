<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\OpenSkill;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Rating\OpenSkill\Gaussian;

/**
 * The normal distribution against reference values of the error function, and the corrections at their limits.
 */
#[CoversClass(Gaussian::class)]
final class GaussianTest extends TestCase
{
    public function testTheComplementaryErrorFunctionMatchesItsReferenceValues(): void
    {
        $references = [
            [0.0, 1.0],
            [0.5, 0.4795001221869535],
            [1.0, 0.15729920705028513],
            [1.5, 0.033894853524689274],
            [1.999, 0.004698443348629487],
            [2.0, 0.004677734981047266],
            [2.5, 0.0004069520174449590],
            [3.0, 2.209049699858544e-05],
            [5.0, 1.5374597944280349e-12],
            [10.0, 2.088487583762545e-45],
            [-1.0, 1.8427007929497148],
        ];

        foreach ($references as [$x, $erfc]) {
            self::assertEqualsWithDelta(1.0, Gaussian::erfc($x) / $erfc, 1e-13, 'erfc('.$x.')');
        }
    }

    public function testTheDistributionIsTheStandardNormal(): void
    {
        self::assertEqualsWithDelta(0.3989422804014327, Gaussian::pdf(0.0), 1e-16);
        self::assertEqualsWithDelta(0.24197072451914337, Gaussian::pdf(1.0), 1e-16);
        self::assertEqualsWithDelta(0.24197072451914337, Gaussian::pdf(-1.0), 1e-16);
        self::assertEqualsWithDelta(0.5, Gaussian::cdf(0.0), 1e-16);
        self::assertEqualsWithDelta(0.8413447460685429, Gaussian::cdf(1.0), 1e-15);
        self::assertEqualsWithDelta(0.15865525393145707, Gaussian::cdf(-1.0), 1e-15);
    }

    public function testTheCorrectionsOfAWin(): void
    {
        // v(0, 0) = φ(0) / Φ(0) and w(0, 0) = v² for an even game.
        $v = 0.3989422804014327 / 0.5;
        [$even, $evenW] = Gaussian::win(0.0, 0.0);
        self::assertEqualsWithDelta($v, $even, 1e-15);
        self::assertEqualsWithDelta($v * $v, $evenW, 1e-15);

        [$won, $wonW] = Gaussian::win(1.0, 0.1);
        $expected = Gaussian::pdf(0.9) / Gaussian::cdf(0.9);
        self::assertEqualsWithDelta($expected, $won, 1e-15);
        self::assertEqualsWithDelta($expected * ($expected + 0.9), $wonW, 1e-15);
    }

    public function testTheCorrectionsOfAnUpsetFarBeyondAFloatAreTheirLimits(): void
    {
        self::assertSame([40.0, 1.0], Gaussian::win(-40.0, 0.0));
        self::assertSame([36.0, 1.0], Gaussian::win(-35.0, 1.0));
        [$v, $w] = Gaussian::win(-34.0, 1.0);
        self::assertEqualsWithDelta(35.03, $v, 0.01);
        self::assertLessThan(1.0, $w);
    }

    public function testTheCorrectionsOfADraw(): void
    {
        self::assertSame(0.0, Gaussian::draw(0.0, 0.1)[0]);
        self::assertLessThan(0.0, Gaussian::draw(0.5, 0.1)[0]);
        self::assertGreaterThan(0.0, Gaussian::draw(-0.5, 0.1)[0]);
        self::assertEqualsWithDelta(-Gaussian::draw(0.5, 0.1)[0], Gaussian::draw(-0.5, 0.1)[0], 1e-15);

        $mass = Gaussian::cdf(0.1 - 0.5) - Gaussian::cdf(-0.1 - 0.5);
        $v = (Gaussian::pdf(-0.6) - Gaussian::pdf(-0.4)) / $mass;
        [$drawn, $drawnW] = Gaussian::draw(0.5, 0.1);
        self::assertEqualsWithDelta($v, $drawn, 1e-14);
        self::assertEqualsWithDelta($v * $v + ((0.1 - 0.5) * Gaussian::pdf(-0.4) + 0.6 * Gaussian::pdf(0.6)) / $mass, $drawnW, 1e-13);
    }

    public function testTheCorrectionsOfADrawFarBeyondAFloatAreTheirLimits(): void
    {
        self::assertSame([-49.9, 1.0], Gaussian::draw(50.0, 0.1));
        self::assertSame([49.9, 1.0], Gaussian::draw(-50.0, 0.1));
    }
}
