<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit\Svg;

use Dom\Element;
use Dom\XMLDocument;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Exception\InvalidDrawing;
use Tilttek\Heraldry\Exception\InvalidPalette;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\ShieldShape;
use Tilttek\Heraldry\Svg\Palette;
use Tilttek\Heraldry\Svg\Shapes;
use Tilttek\Heraldry\Svg\SvgRenderer;
use Tilttek\Heraldry\Tincture;

#[CoversClass(SvgRenderer::class)]
#[CoversClass(Shapes::class)]
#[CoversClass(Palette::class)]
#[CoversClass(InvalidDrawing::class)]
#[CoversClass(InvalidPalette::class)]
final class SvgRendererTest extends TestCase
{
    public function testItDrawsAnAccessibleImage(): void
    {
        $coat = new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup(Charge::Mullet, Tincture::Or, 3));
        $svg = new SvgRenderer()->render($coat, id: 'arms');
        $root = $this->root($svg);

        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 204 244" width="204" height="244" role="img" aria-labelledby="arms-title arms-desc">', $svg);
        self::assertStringContainsString('<title id="arms-title">Coat of arms</title><desc id="arms-desc">Azure, three mullets Or</desc>', $svg);
        self::assertSame('img', $root->getAttribute('role'));
        self::assertStringContainsString('<clipPath id="arms-shield"><path d="'.Shapes::HEATER.'"/></clipPath>', $svg);
        self::assertStringContainsString('<g clip-path="url(#arms-shield)"><path d="'.Shapes::HEATER.'" fill="#1d4f9a"/>', $svg);
        self::assertStringEndsWith('<path d="'.Shapes::HEATER.'" fill="none" stroke="#1c1c1c" stroke-width="3" stroke-linejoin="round"/></svg>', $svg);
        self::assertSame(3, substr_count($svg, '<g transform="translate('));
        self::assertStringContainsString('<g transform="translate(56 69.3) scale(0.52)" fill="#f2c230" stroke="#1c1c1c" stroke-width="2.88">', $svg);
        self::assertStringNotContainsString('<pattern', $svg);
    }

    public function testTheTitleAndDescriptionFollowTheLanguage(): void
    {
        $svg = new SvgRenderer()->render(new Coat(Field::plain(Tincture::Gules)), language: Language::French, id: 'a');

        self::assertStringContainsString('<title id="a-title">Armoiries</title><desc id="a-desc">De gueules plein</desc>', $svg);
    }

    public function testTheTitleIsEscapedAndCleanedOfControlCharacters(): void
    {
        $svg = new SvgRenderer()->render(new Coat(Field::plain(Tincture::Gules)), title: "Arms of <Ann> & \"Bo\"\x01\x7F's\ttable", id: 'a');

        self::assertStringContainsString('<title id="a-title">Arms of &lt;Ann&gt; &amp; &quot;Bo&quot;&apos;s'."\t".'table</title>', $svg);
        $this->root($svg);
    }

    public function testTheIdPrefixIsDerivedFromTheArmsWhenNotGiven(): void
    {
        $renderer = new SvgRenderer();
        $coat = new Coat(Field::plain(Tincture::Gules));
        $id = 'arms-'.substr(hash('xxh3', 'heater Gules'), 0, 12);

        self::assertStringContainsString('aria-labelledby="'.$id.'-title '.$id.'-desc"', $renderer->render($coat));
        self::assertStringContainsString('aria-labelledby="'.$id.'-title', $renderer->render($coat, language: Language::French));
        self::assertNotSame($renderer->render($coat), $renderer->render($coat, ShieldShape::French));
        self::assertStringNotContainsString($id, $renderer->render($coat, ShieldShape::French));
    }

    public function testAnIdPrefixIsALetterThenLettersDigitsDashesOrUnderscores(): void
    {
        $coat = new Coat(Field::plain(Tincture::Gules));
        self::assertStringContainsString('id="a-B_9'.str_repeat('x', 43).'-title"', new SvgRenderer()->render($coat, id: 'a-B_9'.str_repeat('x', 43)));

        foreach (['', '9a', 'a b', 'a"', str_repeat('a', 49), "a\n"] as $id) {
            try {
                new SvgRenderer()->render($coat, id: $id);
                self::fail('The prefix '.$id.' was taken.');
            } catch (InvalidDrawing $invalidDrawing) {
                self::assertSame('The prefix of the ids is a letter followed by up to 47 letters, digits, - or _.', $invalidDrawing->getMessage());
            }
        }
    }

    public function testEachShapeHasItsOutline(): void
    {
        $coat = new Coat(Field::plain(Tincture::Gules));

        foreach (ShieldShape::cases() as $shape) {
            $svg = new SvgRenderer()->render($coat, $shape, id: 'a');
            self::assertSame(3, substr_count($svg, '<path d="'.Shapes::shield($shape).'"'), $shape->value);
        }

        self::assertStringStartsWith('M0,0 H200 V205', Shapes::shield(ShieldShape::French));
        self::assertStringStartsWith('M0,0 H200 V140 A100', Shapes::shield(ShieldShape::Iberian));
        self::assertStringStartsWith('M100,0 L200,120', Shapes::shield(ShieldShape::Lozenge));
    }

    public function testADividedFieldDrawsItsSecondTinctureOverTheFirst(): void
    {
        foreach (Partition::cases() as $partition) {
            $svg = new SvgRenderer()->render(new Coat(Field::divided($partition, Tincture::Or, Tincture::Gules)), id: 'a');

            self::assertStringContainsString('fill="#f2c230"/><path d="'.Shapes::partition($partition).'" fill="#c4161c"/>', $svg);
        }

        self::assertSame('M100,0 H200 V240 H100 Z', Shapes::partition(Partition::PerPale));
        self::assertSame('M0,120 H200 V240 H0 Z', Shapes::partition(Partition::PerFess));
    }

    public function testOrdinariesAreFilledAndOutlined(): void
    {
        foreach (Ordinary::cases() as $ordinary) {
            $svg = new SvgRenderer()->render(new Coat(Field::plain(Tincture::Argent), new Piece($ordinary, Tincture::Gules)), id: 'a');

            foreach (Shapes::ordinary($ordinary) as $path) {
                self::assertStringContainsString('<path d="'.$path.'" fill="#c4161c" stroke="#1c1c1c" stroke-width="1.5"/>', $svg);
            }
        }

        self::assertCount(2, Shapes::ordinary(Ordinary::Cross));
        self::assertCount(2, Shapes::ordinary(Ordinary::Saltire));
        self::assertSame([], Shapes::ordinary(Ordinary::Bordure));
        self::assertStringContainsString(
            '<path d="'.Shapes::HEATER.'" fill="none" stroke="#c4161c" stroke-width="44"/>',
            new SvgRenderer()->render(new Coat(Field::plain(Tincture::Argent), new Piece(Ordinary::Bordure, Tincture::Gules)), id: 'a'),
        );
    }

    public function testChargesGoWhereTheirPlacementSays(): void
    {
        $renderer = new SvgRenderer();
        $azure = Field::plain(Tincture::Azure);
        $spots = static function (string $svg): array {
            preg_match_all('/translate\(([-\d.]+) ([-\d.]+)\) scale\(([\d.]+)\)/', $svg, $matches, \PREG_SET_ORDER);

            return array_map(static fn (array $match): string => $match[1].' '.$match[2].' '.$match[3], $matches);
        };

        self::assertSame(['100 105 0.8'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or)))));
        self::assertSame(['100 63 0.56', '100 151.2 0.56'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 2)))));
        self::assertSame(['58 105 0.56', '142 105 0.56'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 2, Arrangement::InFess)))));
        self::assertSame(['40 105 0.4', '100 105 0.4', '160 105 0.4'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 3, Arrangement::InFess)))));
        self::assertSame(['100 44.1 0.4', '100 105 0.4', '100 165.9 0.4'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 3, Arrangement::InPale)))));
        self::assertSame(['56 69.3 0.52', '144 69.3 0.52', '100 151.2 0.52'], $spots($renderer->render(new Coat($azure, charges: new ChargeGroup(Charge::Roundel, Tincture::Or, 3)))));
        self::assertSame(['100 143 0.62'], $spots($renderer->render(new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Argent)))));
        self::assertSame(['100 110 0.62'], $spots($renderer->render(new Coat($azure, new Piece(Ordinary::Bordure, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Argent)))));
        self::assertSame(['65 33 0.46', '135 33 0.46'], $spots($renderer->render(new Coat($azure, new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Gules, 2, onPiece: true)))));
        self::assertSame(['100 44 0.46', '100 186 0.46'], $spots($renderer->render(new Coat($azure, new Piece(Ordinary::Fess, Tincture::Or), new ChargeGroup(Charge::Roundel, Tincture::Argent, 2)))));
    }

    public function testThePlacesOnAndAroundEachOrdinary(): void
    {
        self::assertSame([[40.0, 115.0, 46.0], [100.0, 115.0, 46.0], [160.0, 115.0, 46.0]], Shapes::onOrdinary(Ordinary::Fess, 3));
        self::assertSame([[100.0, 115.0, 46.0]], Shapes::onOrdinary(Ordinary::Pale, 1));
        self::assertSame([[100.0, 70.0, 46.0], [100.0, 160.0, 46.0]], Shapes::onOrdinary(Ordinary::Pale, 2));
        self::assertSame([[100.0, 45.0, 46.0], [100.0, 115.0, 46.0], [100.0, 185.0, 46.0]], Shapes::onOrdinary(Ordinary::Pale, 3));
        self::assertSame([[86.0, 103.2, 38.0]], Shapes::onOrdinary(Ordinary::Bend, 1));
        self::assertEqualsWithDelta([[60.0, 72.0, 38.0], [112.0, 134.4, 38.0]], Shapes::onOrdinary(Ordinary::Bend, 2), 1.0E-9);
        self::assertEqualsWithDelta([[40.0, 48.0, 38.0], [86.0, 103.2, 38.0], [132.0, 158.4, 38.0]], Shapes::onOrdinary(Ordinary::Bend, 3), 1.0E-9);
        self::assertSame([[160.0, 48.0, 38.0], [114.0, 103.2, 38.0], [68.0, 158.4, 38.0]], Shapes::onOrdinary(Ordinary::BendSinister, 3));
        self::assertSame([[100.0, 94.0, 32.0]], Shapes::onOrdinary(Ordinary::Chevron, 1));
        self::assertCount(3, Shapes::onOrdinary(Ordinary::Chevron, 3));
        self::assertSame([[100.0, 115.0, 42.0]], Shapes::onOrdinary(Ordinary::Cross, 1));
        self::assertSame([[100.0, 120.0, 38.0]], Shapes::onOrdinary(Ordinary::Saltire, 1));
        self::assertSame([], Shapes::onOrdinary(Ordinary::Bordure, 1));
        self::assertSame([[55.0, 44.0, 44.0], [145.0, 44.0, 44.0], [100.0, 186.0, 44.0]], Shapes::aroundOrdinary(Ordinary::Fess, 3));
        self::assertSame([[36.0, 110.0, 44.0], [164.0, 110.0, 44.0]], Shapes::aroundOrdinary(Ordinary::Pale, 2));
        self::assertSame([[150.0, 62.0, 46.0], [52.0, 172.0, 46.0]], Shapes::aroundOrdinary(Ordinary::Bend, 2));
        self::assertSame([[50.0, 62.0, 46.0], [148.0, 172.0, 46.0]], Shapes::aroundOrdinary(Ordinary::BendSinister, 2));
        self::assertSame([[46.0, 52.0, 40.0], [154.0, 52.0, 40.0], [100.0, 178.0, 40.0]], Shapes::aroundOrdinary(Ordinary::Chevron, 3));
        self::assertSame([], Shapes::aroundOrdinary(Ordinary::Chief, 2));
    }

    public function testVoidedChargesAreFilledEvenOdd(): void
    {
        foreach (Charge::cases() as $charge) {
            $svg = new SvgRenderer()->render(new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup($charge, Tincture::Or)), id: 'a');
            [$paths, $evenOdd] = Shapes::charge($charge);

            self::assertSame(\in_array($charge, [Charge::Annulet, Charge::Mascle], true), $evenOdd, $charge->value);
            self::assertSame($evenOdd, str_contains($svg, 'fill-rule="evenodd"'), $charge->value);

            foreach ($paths as $path) {
                self::assertStringContainsString('<path d="'.$path.'"/>', $svg);
            }
        }

        self::assertCount(7, Shapes::charge(Charge::FleurDeLis)[0]);
        self::assertSame(['M0,-50 L11.23,-15.45 L47.55,-15.45 L18.17,5.9 L29.39,40.45 L0,19.1 L-29.39,40.45 L-18.17,5.9 L-47.55,-15.45 L-11.23,-15.45 Z'], Shapes::charge(Charge::Mullet)[0]);
    }

    public function testBothFursAreDefinedWhenBothAreUsed(): void
    {
        $svg = new SvgRenderer()->render(new Coat(Field::divided(Partition::PerFess, Tincture::Ermine, Tincture::Gules), new Piece(Ordinary::Chief, Tincture::Vair)), id: 'a');

        self::assertSame(1, substr_count($svg, '<pattern id="a-ermine"'));
        self::assertSame(1, substr_count($svg, '<pattern id="a-vair"'));
    }

    public function testFursAreDrawnWithPatternsOnlyWhenUsed(): void
    {
        $renderer = new SvgRenderer();
        $ermine = $renderer->render(new Coat(Field::plain(Tincture::Ermine)), id: 'a');
        $vair = $renderer->render(new Coat(Field::plain(Tincture::Gules), new Piece(Ordinary::Chief, Tincture::Vair)), id: 'a');

        self::assertStringContainsString('<pattern id="a-ermine" width="40" height="44" patternUnits="userSpaceOnUse"><rect width="40" height="44" fill="#f7f7f2"/>', $ermine);
        self::assertStringContainsString('fill="url(#a-ermine)"', $ermine);
        self::assertStringNotContainsString('a-vair', $ermine);
        self::assertStringContainsString('<pattern id="a-vair" width="40" height="40" patternUnits="userSpaceOnUse"><rect width="40" height="40" fill="#1d4f9a"/>', $vair);
        self::assertStringContainsString('fill="url(#a-vair)"', $vair);
        self::assertStringNotContainsString('a-ermine', $vair);
        self::assertStringContainsString('a-ermine', $renderer->render(new Coat(Field::plain(Tincture::Gules), charges: new ChargeGroup(Charge::Roundel, Tincture::Ermine)), id: 'a'));
        self::assertStringContainsString('a-vair', $renderer->render(new Coat(Field::plain(Tincture::Gules), brisure: new Brisure(BrisureMark::Label, Tincture::Vair)), id: 'a'));
        self::assertStringContainsString('a-vair', $renderer->render(new Coat(Field::divided(Partition::PerFess, Tincture::Gules, Tincture::Vair)), id: 'a'));
    }

    public function testMarksOfDifferenceAreDrawnLast(): void
    {
        $svg = new SvgRenderer()->render(new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup(Charge::Mullet, Tincture::Or), brisure: new Brisure(BrisureMark::Label, Tincture::Gules)), id: 'a');

        foreach (Shapes::brisure(BrisureMark::Label) as $path) {
            self::assertStringContainsString('<path d="'.$path.'" fill="#c4161c" stroke="#1c1c1c" stroke-width="1.5"/>', $svg);
        }

        self::assertGreaterThan(strpos($svg, '<g transform'), strpos($svg, Shapes::brisure(BrisureMark::Label)[0]));
        self::assertCount(1, Shapes::brisure(BrisureMark::Bendlet));
    }

    public function testAPaletteChangesTheColours(): void
    {
        $palette = new Palette(['azure' => '#0000FF'], '#333333');
        $svg = new SvgRenderer($palette)->render(new Coat(Field::plain(Tincture::Azure), new Piece(Ordinary::Fess, Tincture::Or)), id: 'a');

        self::assertStringContainsString('fill="#0000FF"', $svg);
        self::assertStringContainsString('fill="#f2c230" stroke="#333333"', $svg);
        self::assertSame('#f7f7f2', $palette->colour(Tincture::Ermine));
        self::assertSame('#f7f7f2', $palette->colour(Tincture::Vair));
        self::assertSame('#6b2d73', $palette->colour(Tincture::Purpure));
    }

    public function testAPaletteTakesOnlyMetalsAndColoursInHexadecimal(): void
    {
        $refusals = [];

        foreach ([[['ermine' => '#ffffff'], '#000000'], [['or' => 'gold'], '#000000'], [['or' => '#fff'], '#000000'], [[], 'black'], [['or' => "#ffffff\n"], '#000000']] as [$colours, $outline]) {
            try {
                new Palette($colours, $outline);
            } catch (InvalidPalette $invalidPalette) {
                $refusals[] = $invalidPalette->getMessage();
            }
        }

        self::assertSame([
            'A palette gives the colours of the metals and colours; ermine is not one.',
            'A colour is written #rrggbb; gold is not.',
            'A colour is written #rrggbb; #fff is not.',
            'A colour is written #rrggbb; black is not.',
            "A colour is written #rrggbb; #ffffff\n is not.",
        ], $refusals);
    }

    public function testNumbersAreWrittenShort(): void
    {
        self::assertSame('0', Shapes::number(0.0));
        self::assertSame('0', Shapes::number(-0.001));
        self::assertSame('12', Shapes::number(12.0));
        self::assertSame('12.5', Shapes::number(12.5));
        self::assertSame('-3.14', Shapes::number(-3.14159));
        self::assertSame('10', Shapes::number(9.999));
        self::assertSame('M-45,0 a45,45 0 1 0 90,0 a45,45 0 1 0 -90,0 Z', Shapes::circle(0, 0, 45));
        self::assertSame('M2,-7 a2.2,2.2 0 1 0 4.4,0 a2.2,2.2 0 1 0 -4.4,0 Z', Shapes::circle(4.2, -7, 2.2));
    }

    /**
     * Every image the renderer draws is well-formed XML that a strict sanitizer keeps whole, once allowed the two
     * accessibility attributes and the ids as written.
     */
    public function testEveryDrawingPassesTheSanitizerUntouched(): void
    {
        $sanitizer = new SvgSanitizer(new Policy()->withIdPrefix(null)->withAttributes('role', 'aria-labelledby'));
        $renderer = new SvgRenderer();
        $generator = new CoatGenerator();
        $shapes = ShieldShape::cases();

        for ($i = 0; $i < 400; ++$i) {
            $coat = $generator->generate(Seed::fromHex(hash('sha256', 'svg '.$i)));
            $svg = $renderer->render($coat, $shapes[$i % 4], 0 === $i % 2 ? Language::English : Language::French);
            $sanitized = $sanitizer->sanitize($svg);

            self::assertTrue($sanitized->isClean(), $svg.' lost '.json_encode($sanitized->removals));
            $this->root($svg);
        }
    }

    private function root(string $svg): Element
    {
        $root = XMLDocument::createFromString($svg)->documentElement;
        self::assertInstanceOf(Element::class, $root);
        self::assertSame('svg', $root->localName);

        return $root;
    }
}
