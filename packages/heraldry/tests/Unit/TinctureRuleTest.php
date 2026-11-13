<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\Tincture;
use Tilttek\Heraldry\TinctureRule;
use Tilttek\Heraldry\Violation;

#[CoversClass(TinctureRule::class)]
#[CoversClass(Violation::class)]
final class TinctureRuleTest extends TestCase
{
    public function testWellKnownArmsKeepTheRule(): void
    {
        $rule = new TinctureRule();

        self::assertTrue($rule->respects(new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup(Charge::FleurDeLis, Tincture::Or, 3))));
        self::assertTrue($rule->respects(new Coat(Field::plain(Tincture::Argent), new Piece(Ordinary::Cross, Tincture::Gules))));
        self::assertTrue($rule->respects(new Coat(Field::plain(Tincture::Ermine))));
        self::assertTrue($rule->respects(new Coat(Field::plain(Tincture::Or), new Piece(Ordinary::Fess, Tincture::Ermine))));
    }

    public function testMetalOnMetalAndColourOnColourAreViolations(): void
    {
        $rule = new TinctureRule();
        $coat = new Coat(
            Field::plain(Tincture::Argent),
            new Piece(Ordinary::Chief, Tincture::Or),
            new ChargeGroup(Charge::Mullet, Tincture::Argent, 3, onPiece: true),
        );

        self::assertEquals([
            new Violation('piece', Tincture::Or, Tincture::Argent),
            new Violation('charges', Tincture::Argent, Tincture::Or),
        ], $rule->violations($coat));
        self::assertFalse($rule->respects($coat));
        self::assertSame('The ordinary lies or on argent: metal on metal.', $rule->violations($coat)[0]->describe());
        self::assertSame('The charges lie argent on or: metal on metal.', $rule->violations($coat)[1]->describe());
        self::assertSame('The charges lie gules on azure: colour on colour.', new Violation('charges', Tincture::Gules, Tincture::Azure)->describe());
        self::assertSame('The charges lie ermine on ermine: a tincture on itself.', new Violation('charges', Tincture::Ermine, Tincture::Ermine)->describe());
    }

    public function testChargesBesideAnOrdinaryLieOnTheField(): void
    {
        $coat = new Coat(Field::plain(Tincture::Azure), new Piece(Ordinary::Fess, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, 3));

        self::assertEquals([new Violation('charges', Tincture::Gules, Tincture::Azure)], new TinctureRule()->violations($coat));
    }

    public function testADividedFieldAndAMarkOfDifferenceAreNotBound(): void
    {
        $rule = new TinctureRule();
        $divided = Field::divided(Partition::PerPale, Tincture::Or, Tincture::Gules);

        self::assertTrue($rule->respects(new Coat($divided, new Piece(Ordinary::Fess, Tincture::Argent), new ChargeGroup(Charge::Mullet, Tincture::Azure, 3))));
        self::assertTrue($rule->respects(new Coat($divided, charges: new ChargeGroup(Charge::Mullet, Tincture::Or))));
        self::assertFalse($rule->respects(new Coat($divided, new Piece(Ordinary::Fess, Tincture::Argent), new ChargeGroup(Charge::Mullet, Tincture::Or, 3, onPiece: true))));
        self::assertTrue($rule->respects(new Coat(Field::plain(Tincture::Or), brisure: new Brisure(BrisureMark::Label, Tincture::Argent))));
    }

    /**
     * The property: for every plain field, every ordinary and every tincture of it, and every tincture of charges on it
     * and beside it, a violation is reported exactly where the tinctures do not contrast.
     */
    public function testEveryCombinationIsJudgedByTheContrastOfItsLayers(): void
    {
        $rule = new TinctureRule();

        foreach ($this->combinations() as [$field, $pieceTincture, $chargeTincture, $onPiece]) {
            $piece = new Piece($onPiece ? Ordinary::Chief : Ordinary::Bordure, $pieceTincture);
            $coat = new Coat(Field::plain($field), $piece, new ChargeGroup(Charge::Roundel, $chargeTincture, 2, onPiece: $onPiece));
            $layers = array_map(static fn (Violation $violation): string => $violation->layer, $rule->violations($coat));
            $expected = [];

            if (!$pieceTincture->contrastsWith($field)) {
                $expected[] = 'piece';
            }

            if (!$chargeTincture->contrastsWith($onPiece ? $pieceTincture : $field)) {
                $expected[] = 'charges';
            }

            self::assertSame($expected, $layers);
        }
    }

    /**
     * @return iterable<array{Tincture, Tincture, Tincture, bool}>
     */
    private function combinations(): iterable
    {
        $tinctures = Tincture::cases();

        foreach ($tinctures as $field) {
            foreach ($tinctures as $piece) {
                foreach ($tinctures as $charges) {
                    yield [$field, $piece, $charges, true];
                    yield [$field, $piece, $charges, false];
                }
            }
        }
    }
}
