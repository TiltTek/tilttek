<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\BrisureMark;
use Tilttek\Heraldry\Charge;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Exception\InvalidCoat;
use Tilttek\Heraldry\Field;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Ordinary;
use Tilttek\Heraldry\Partition;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\Tincture;
use Tilttek\Heraldry\TinctureKind;

#[CoversClass(Tincture::class)]
#[CoversClass(Field::class)]
#[CoversClass(Ordinary::class)]
#[CoversClass(Piece::class)]
#[CoversClass(ChargeGroup::class)]
#[CoversClass(Coat::class)]
#[CoversClass(Brisure::class)]
#[CoversClass(Language::class)]
#[CoversClass(InvalidCoat::class)]
final class ModelTest extends TestCase
{
    public function testTinctureKinds(): void
    {
        self::assertSame([Tincture::Or, Tincture::Argent], Tincture::ofKind(TinctureKind::Metal));
        self::assertSame([Tincture::Gules, Tincture::Azure, Tincture::Vert, Tincture::Sable, Tincture::Purpure], Tincture::ofKind(TinctureKind::Colour));
        self::assertSame([Tincture::Ermine, Tincture::Vair], Tincture::ofKind(TinctureKind::Fur));
    }

    public function testTheContrastOfEveryPairFollowsTheRule(): void
    {
        foreach (Tincture::cases() as $tincture) {
            foreach (Tincture::cases() as $ground) {
                $expected = $tincture !== $ground && (TinctureKind::Fur === $tincture->kind() || $tincture->kind() !== $ground->kind());
                self::assertSame($expected, $tincture->contrastsWith($ground), $tincture->value.' on '.$ground->value);
            }
        }

        self::assertTrue(Tincture::Or->contrastsWith(Tincture::Azure));
        self::assertTrue(Tincture::Gules->contrastsWith(Tincture::Argent));
        self::assertFalse(Tincture::Or->contrastsWith(Tincture::Argent));
        self::assertFalse(Tincture::Gules->contrastsWith(Tincture::Sable));
        self::assertTrue(Tincture::Ermine->contrastsWith(Tincture::Argent));
        self::assertTrue(Tincture::Vair->contrastsWith(Tincture::Ermine));
        self::assertFalse(Tincture::Ermine->contrastsWith(Tincture::Ermine));
        self::assertTrue(Tincture::Gules->contrastsWith(Tincture::Ermine));
    }

    public function testFields(): void
    {
        $plain = Field::plain(Tincture::Azure);
        $divided = Field::divided(Partition::Quarterly, Tincture::Or, Tincture::Gules);

        self::assertTrue($plain->isPlain());
        self::assertNull($plain->partition);
        self::assertSame(Tincture::Azure, $plain->tincture());
        self::assertSame([Tincture::Azure], $plain->tinctures);
        self::assertFalse($divided->isPlain());
        self::assertSame(Partition::Quarterly, $divided->partition);
        self::assertSame([Tincture::Or, Tincture::Gules], $divided->tinctures);
        self::assertSame(Tincture::Or, $divided->tincture());
        self::assertTrue($divided->has(Tincture::Gules));
        self::assertFalse($divided->has(Tincture::Azure));
    }

    public function testADividedFieldHasTwoTinctures(): void
    {
        try {
            Field::divided(Partition::PerPale, Tincture::Or, Tincture::Or);
            self::fail('No exception was thrown.');
        } catch (InvalidCoat $invalidCoat) {
            self::assertSame('A divided field has two different tinctures.', $invalidCoat->getMessage());
        }
    }

    public function testWhatEachOrdinaryCarriesAndSurrounds(): void
    {
        self::assertSame([1, 2, 3], Ordinary::Chief->chargesOn());
        self::assertSame([1, 3], Ordinary::Chevron->chargesOn());
        self::assertSame([1], Ordinary::Cross->chargesOn());
        self::assertSame([], Ordinary::Bordure->chargesOn());
        self::assertSame([1, 2, 3], Ordinary::Bordure->chargesBeside());
        self::assertSame([2, 3], Ordinary::Fess->chargesBeside());
        self::assertSame([2], Ordinary::Pale->chargesBeside());
        self::assertSame([3], Ordinary::Chevron->chargesBeside());
        self::assertSame([], Ordinary::Saltire->chargesBeside());
        self::assertFalse(Ordinary::Chief->surroundsCharges());
        self::assertFalse(Ordinary::Bordure->surroundsCharges());
        self::assertTrue(Ordinary::Fess->surroundsCharges());
        self::assertTrue(Ordinary::Cross->surroundsCharges());
    }

    public function testAGroupHoldsOneToThreeCharges(): void
    {
        $group = new ChargeGroup(Charge::Mullet, Tincture::Or, 3);

        self::assertSame(3, $group->count);
        self::assertSame(Arrangement::Usual, $group->placement());
        self::assertFalse($group->onPiece);
        self::assertSame(Arrangement::InPale, new ChargeGroup(Charge::Mullet, Tincture::Or, 2)->placement());
        self::assertSame(Arrangement::InFess, new ChargeGroup(Charge::Mullet, Tincture::Or, 2, Arrangement::InFess)->placement());
        self::assertSame(Arrangement::InPale, new ChargeGroup(Charge::Mullet, Tincture::Or, 3, Arrangement::InPale)->placement());
        self::assertSame(Arrangement::Usual, new ChargeGroup(Charge::Mullet, Tincture::Or)->placement());

        foreach ([0, 4] as $count) {
            try {
                new ChargeGroup(Charge::Mullet, Tincture::Or, $count);
                self::fail('A group of '.$count.' was taken.');
            } catch (InvalidCoat $invalidCoat) {
                self::assertSame('A group holds 1 to 3 charges; '.$count.' is not.', $invalidCoat->getMessage());
            }
        }
    }

    public function testASingleChargeHasNoArrangement(): void
    {
        try {
            new ChargeGroup(Charge::Mullet, Tincture::Or, 1, Arrangement::InFess);
            self::fail('No exception was thrown.');
        } catch (InvalidCoat $invalidCoat) {
            self::assertSame('A single charge has no arrangement.', $invalidCoat->getMessage());
        }
    }

    public function testACoatHoldsItsParts(): void
    {
        $field = Field::plain(Tincture::Azure);
        $piece = new Piece(Ordinary::Fess, Tincture::Or);
        $charges = new ChargeGroup(Charge::Mullet, Tincture::Argent, 3);
        $brisure = new Brisure(BrisureMark::Label, Tincture::Gules);
        $coat = new Coat($field, $piece, $charges, $brisure);

        self::assertSame($field, $coat->field);
        self::assertSame($piece, $coat->piece);
        self::assertSame($charges, $coat->charges);
        self::assertSame($brisure, $coat->brisure);
        self::assertNull(new Coat($field)->piece);
    }

    /**
     * Every ordinary and every count, on it and beside it: taken exactly when the ordinary allows it.
     *
     * @return iterable<string, array{Ordinary, int, bool}>
     */
    public static function counts(): iterable
    {
        foreach (Ordinary::cases() as $ordinary) {
            foreach ([1, 2, 3] as $count) {
                yield $ordinary->value.', '.$count.' on it' => [$ordinary, $count, true];
                yield $ordinary->value.', '.$count.' beside it' => [$ordinary, $count, false];
            }
        }
    }

    #[DataProvider('counts')]
    public function testAnOrdinaryTakesTheChargesItAllows(Ordinary $ordinary, int $count, bool $onPiece): void
    {
        $allowed = \in_array($count, $onPiece ? $ordinary->chargesOn() : $ordinary->chargesBeside(), true);

        try {
            new Coat(Field::plain(Tincture::Azure), new Piece($ordinary, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, $count, onPiece: $onPiece));
            self::assertTrue($allowed);
        } catch (InvalidCoat $invalidCoat) {
            self::assertFalse($allowed);
            self::assertStringStartsWith('A '.str_replace('_', ' ', $ordinary->value).' takes ', $invalidCoat->getMessage());
        }
    }

    public function testTheRefusalSaysWhatTheOrdinaryTakes(): void
    {
        $messages = [];

        foreach ([[Ordinary::Fess, 1, false], [Ordinary::Chevron, 2, true], [Ordinary::Cross, 2, false], [Ordinary::BendSinister, 3, false]] as [$ordinary, $count, $onPiece]) {
            try {
                new Coat(Field::plain(Tincture::Azure), new Piece($ordinary, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, $count, onPiece: $onPiece));
            } catch (InvalidCoat $invalidCoat) {
                $messages[] = $invalidCoat->getMessage();
            }
        }

        self::assertSame([
            'A fess takes 2 or 3 charges beside it; 1 is not.',
            'A chevron takes 1 or 3 charges on itself; 2 is not.',
            'A cross takes no charges beside it; 2 is not.',
            'A bend sinister takes 2 charges beside it; 3 is not.',
        ], $messages);
    }

    public function testChargesOnAnOrdinaryNeedOne(): void
    {
        try {
            new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup(Charge::Mullet, Tincture::Or, onPiece: true));
            self::fail('No exception was thrown.');
        } catch (InvalidCoat $invalidCoat) {
            self::assertSame('Charges on an ordinary need an ordinary.', $invalidCoat->getMessage());
        }
    }

    public function testChargesOnOrAroundAnOrdinaryTakeNoArrangement(): void
    {
        foreach ([[Ordinary::Fess, false], [Ordinary::Chief, true]] as [$ordinary, $onPiece]) {
            try {
                new Coat(Field::plain(Tincture::Azure), new Piece($ordinary, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Gules, 3, Arrangement::InFess, $onPiece));
                self::fail('An arrangement was taken.');
            } catch (InvalidCoat $invalidCoat) {
                self::assertSame('Charges on or around an ordinary follow it: they take no arrangement of their own.', $invalidCoat->getMessage());
            }
        }

        $beside = new Coat(Field::plain(Tincture::Azure), new Piece(Ordinary::Chief, Tincture::Or), new ChargeGroup(Charge::Mullet, Tincture::Argent, 3, Arrangement::InFess));
        self::assertSame(Arrangement::InFess, $beside->charges?->arrangement);
        self::assertSame(Arrangement::InPale, new Coat(Field::plain(Tincture::Azure), charges: new ChargeGroup(Charge::Mullet, Tincture::Or, 3, Arrangement::InPale))->charges?->arrangement);
    }

    public function testALocaleNamesItsLanguage(): void
    {
        self::assertSame(Language::French, Language::ofLocale('fr_CA'));
        self::assertSame(Language::French, Language::ofLocale('FR'));
        self::assertSame(Language::English, Language::ofLocale('en'));
        self::assertSame(Language::English, Language::ofLocale('es'));
    }
}
