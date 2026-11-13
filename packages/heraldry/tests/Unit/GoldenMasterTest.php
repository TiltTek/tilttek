<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Blazon\EnglishBlazon;
use Tilttek\Heraldry\Blazon\FrenchBlazon;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Svg\Shapes;
use Tilttek\Heraldry\Svg\SvgRenderer;

/**
 * The arms, blazons and drawings of a thousand fixed seeds, held by their SHA-256: the same seed must give the same
 * arms on every server and in every version, so any change to what is drawn, written or rendered shows here first.
 * When such a change is meant, the hashes are computed anew and the change goes into the CHANGELOG.
 */
#[CoversClass(CoatGenerator::class)]
#[CoversClass(Seed::class)]
#[CoversClass(Blazonry::class)]
#[CoversClass(EnglishBlazon::class)]
#[CoversClass(FrenchBlazon::class)]
#[CoversClass(SvgRenderer::class)]
#[CoversClass(Shapes::class)]
final class GoldenMasterTest extends TestCase
{
    public function testAThousandSeedsGiveTheSameArmsBlazonsAndDrawings(): void
    {
        $generator = new CoatGenerator();
        $blazonry = new Blazonry();
        $renderer = new SvgRenderer();
        $shapes = ShieldShape::cases();
        $english = '';
        $french = '';
        $drawings = '';

        for ($i = 0; $i < 1_000; ++$i) {
            $coat = $generator->generate(Seed::fromHex(hash('sha256', 'golden '.$i)));
            $english .= $blazonry->write($coat)."\n";
            $french .= $blazonry->write($coat, Language::French)."\n";

            if ($i < 200) {
                $drawings .= $renderer->render($coat, $shapes[$i % 4]);
            }
        }

        self::assertSame('bf121b3c4d1827d770d1f0d11b748f7ba992d7484c1914f6b69da560c099a765', hash('sha256', $english));
        self::assertSame('f70752ffbb0fb9a028657a300471810d879126be9b2f101217ebb86307cec25b', hash('sha256', $french));
        self::assertSame('0d2523894eb2316a087a036de04246e955dc356ce08e004415cf2846626063ff', hash('sha256', $drawings));
    }

    public function testWithoutFursTheSeedsGiveOtherFixedArms(): void
    {
        $generator = new CoatGenerator(furs: false);
        $blazonry = new Blazonry();
        $english = '';

        for ($i = 0; $i < 300; ++$i) {
            $english .= $blazonry->write($generator->generate(Seed::fromHex(hash('sha256', 'golden '.$i))))."\n";
        }

        self::assertSame('0df94e3fd74b6509f020f5aed0f1940d98d2f09bc4a2270d65cb985c7069a7b5', hash('sha256', $english));
    }
}
