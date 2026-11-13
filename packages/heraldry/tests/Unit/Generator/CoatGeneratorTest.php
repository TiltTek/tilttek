<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Heraldry\Arrangement;
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Brisure;
use Tilttek\Heraldry\ChargeGroup;
use Tilttek\Heraldry\Coat;
use Tilttek\Heraldry\Exception\InvalidSeed;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Piece;
use Tilttek\Heraldry\TinctureKind;
use Tilttek\Heraldry\TinctureRule;

#[CoversClass(CoatGenerator::class)]
#[CoversClass(Seed::class)]
#[CoversClass(InvalidSeed::class)]
final class CoatGeneratorTest extends TestCase
{
    private const int SEEDS = 3_000;

    public function testASeedIsWrittenAndReadAsHexadecimal(): void
    {
        $seed = Seed::fromHex(str_repeat('aB', 32));

        self::assertSame(str_repeat("\xab", 32), $seed->bytes);
        self::assertSame(str_repeat('ab', 32), $seed->hex());
        self::assertSame(Seed::BYTES, \strlen(Seed::generate()->bytes));
        self::assertNotSame(Seed::generate()->hex(), Seed::generate()->hex());
        self::assertSame(64, \strlen(Seed::generate()->hex()));
    }

    public function testASeedIsSixtyFourHexadecimalDigits(): void
    {
        foreach ([str_repeat('a', 63), str_repeat('a', 65), str_repeat('g', 64), str_repeat('a', 64)."\n", ''] as $text) {
            try {
                Seed::fromHex($text);
                self::fail('The seed '.$text.' was taken.');
            } catch (InvalidSeed $invalidSeed) {
                self::assertSame('A seed is written as 64 hexadecimal digits: the 32 bytes it is made of.', $invalidSeed->getMessage());
            }
        }
    }

    public function testTheRandomizerIsTheSameForTheSameSeed(): void
    {
        $seed = Seed::fromHex(str_repeat('00', 32));

        self::assertSame($seed->randomizer()->getBytes(16), $seed->randomizer()->getBytes(16));
        self::assertNotSame($seed->randomizer()->getBytes(16), Seed::fromHex(str_repeat('01', 32))->randomizer()->getBytes(16));
    }

    public function testKnownSeedsGiveKnownArms(): void
    {
        $generator = new CoatGenerator();
        $blazonry = new Blazonry();
        $arms = static fn (string $byte): string => $blazonry->write($generator->generate(Seed::fromHex(str_repeat($byte, 32))));

        self::assertSame('Per fess azure and Or, two crescents in pale argent', $arms('00'));
        self::assertSame('Per fess azure and Or, a bordure sable', $arms('01'));
        self::assertSame('Purpure, on a bend sinister Or a lozenge azure', $arms('2a'));
        self::assertSame('Vert, a chevron argent', $arms('ff'));
    }

    /**
     * The property of reproducibility: every seed gives the same arms each time, and the arms are a function of the
     * seed alone, not of the generator object.
     */
    public function testTheSameSeedAlwaysGivesTheSameArms(): void
    {
        for ($i = 0; $i < 300; ++$i) {
            $seed = Seed::generate();
            $first = new CoatGenerator()->generate($seed);
            $again = new CoatGenerator()->generate(Seed::fromHex($seed->hex()));

            self::assertEquals($first, $again);
        }
    }

    /**
     * The property of the rule of tincture: no generated coat breaks it, and no element lies on a tincture it shares.
     */
    public function testEveryGeneratedCoatKeepsTheRuleOfTincture(): void
    {
        $rule = new TinctureRule();
        $generator = new CoatGenerator();

        foreach ($this->coats($generator) as $hex => $coat) {
            self::assertSame([], $rule->violations($coat), $hex);
            $field = $coat->field;

            if ($coat->piece instanceof Piece) {
                self::assertFalse($field->has($coat->piece->tincture), $hex);
            }

            if ($coat->charges instanceof ChargeGroup && !$coat->charges->onPiece) {
                self::assertFalse($field->has($coat->charges->tincture), $hex);
                self::assertNotSame($coat->piece?->tincture, $coat->charges->tincture, $hex);
            }

            if ($coat->brisure instanceof Brisure) {
                self::assertFalse($field->has($coat->brisure->tincture), $hex);
                self::assertNotSame($coat->piece?->tincture, $coat->brisure->tincture, $hex);
            }

            if (!$field->isPlain()) {
                self::assertNotSame($field->tinctures[0]->kind(), $field->tinctures[1]->kind(), $hex);
                self::assertContains(TinctureKind::Colour, [$field->tinctures[0]->kind(), $field->tinctures[1]->kind()], $hex);
            }
        }
    }

    public function testTheGeneratorReachesEveryPartOfTheModel(): void
    {
        $seen = [];
        $plain = 0;
        $pieces = 0;
        $brisures = 0;

        foreach ($this->coats(new CoatGenerator()) as $coat) {
            $plain += $coat->field->isPlain() ? 1 : 0;
            $pieces += $coat->piece instanceof Piece ? 1 : 0;
            $brisures += $coat->brisure instanceof Brisure ? 1 : 0;

            foreach ($coat->field->tinctures as $tincture) {
                $seen['field '.$tincture->value] = true;
            }

            if (null !== $coat->field->partition) {
                $seen['partition '.$coat->field->partition->value] = true;
                $seen['first '.$coat->field->tinctures[0]->kind()->value] = true;
            }

            if ($coat->piece instanceof Piece) {
                $seen['ordinary '.$coat->piece->ordinary->value] = true;
            }

            if ($coat->charges instanceof ChargeGroup) {
                $seen['charge '.$coat->charges->charge->value] = true;
                $seen['count '.$coat->charges->count] = true;
                $seen['arrangement '.$coat->charges->arrangement->value] = true;
                $seen[($coat->charges->onPiece ? 'on ' : 'beside ').($coat->piece->ordinary->value ?? 'nothing')] = true;
            }

            if ($coat->brisure instanceof Brisure) {
                $seen['brisure '.$coat->brisure->mark->value] = true;
            }
        }

        // 9 field tinctures, 6 partitions, 3 first tinctures, 9 ordinaries, 8 charges, 3 counts, 3 arrangements,
        // 8 ordinaries carrying charges, 7 with charges beside them, charges with no ordinary, 2 brisures.
        self::assertCount(9 + 6 + 3 + 9 + 8 + 3 + 3 + 8 + 7 + 1 + 2, $seen);
        self::assertEqualsWithDelta(0.6, $plain / self::SEEDS, 0.04);
        self::assertEqualsWithDelta(0.55, $pieces / self::SEEDS, 0.04);
        self::assertEqualsWithDelta(0.1, $brisures / self::SEEDS, 0.025);
    }

    public function testFursCanBeLeftOut(): void
    {
        foreach ($this->coats(new CoatGenerator(furs: false)) as $hex => $coat) {
            foreach ($coat->field->tinctures as $tincture) {
                self::assertNotSame(TinctureKind::Fur, $tincture->kind(), $hex);
            }
        }
    }

    public function testCoatsWithoutOrdinaryUsuallyHaveCharges(): void
    {
        $bare = 0;
        $alone = 0;
        $arranged = 0;
        $multiple = 0;

        foreach ($this->coats(new CoatGenerator()) as $coat) {
            if (!$coat->piece instanceof Piece) {
                ++$alone;
                $bare += $coat->charges instanceof ChargeGroup ? 0 : 1;

                if ($coat->charges instanceof ChargeGroup && $coat->charges->count > 1) {
                    ++$multiple;
                    $arranged += Arrangement::Usual === $coat->charges->arrangement ? 0 : 1;
                }
            }
        }

        self::assertEqualsWithDelta(0.15, $bare / $alone, 0.04);
        self::assertEqualsWithDelta(0.3, $arranged / $multiple, 0.06);
    }

    public function testTheBlazonOfEveryGeneratedCoatIsWritten(): void
    {
        $blazonry = new Blazonry();

        foreach ($this->coats(new CoatGenerator()) as $coat) {
            self::assertNotSame('', $blazonry->write($coat));
            self::assertMatchesRegularExpression('/\A[A-ZÉ]/u', $blazonry->write($coat, Language::French));
        }
    }

    public function testChargesOnAnOrdinaryThatTakesBothAreOnItWhenTheDrawIsTheLimit(): void
    {
        // This seed's draw for the choice is exactly 50, the last that counts: the charges go on the chevron.
        $coat = new CoatGenerator()->generate(Seed::fromHex(str_pad('5f', 64, '0', \STR_PAD_LEFT)));

        self::assertInstanceOf(ChargeGroup::class, $coat->charges);
        self::assertTrue($coat->charges->onPiece);
        self::assertSame('Per bend sinister argent and vert, on a chevron sable three annulets argent', new Blazonry()->write($coat));
    }

    /**
     * @return iterable<string, Coat>
     */
    private function coats(CoatGenerator $generator): iterable
    {
        for ($i = 0; $i < self::SEEDS; ++$i) {
            $hex = hash('sha256', 'seed '.$i);

            yield $hex => $generator->generate(Seed::fromHex($hex));
        }
    }
}
