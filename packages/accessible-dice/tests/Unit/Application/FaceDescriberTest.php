<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\AccessibleDice\Application\FaceDescriber;
use Tilttek\AccessibleDice\Application\FaceDescription;
use Tilttek\AccessibleDice\Application\Words;
use Tilttek\AccessibleDice\Tests\Unit\Fixture\Dice;
use Tilttek\Dice\Exception\CannotRoll;

#[CoversClass(FaceDescriber::class)]
#[CoversClass(FaceDescription::class)]
#[CoversClass(Words::class)]
final class FaceDescriberTest extends TestCase
{
    public function testEachFaceIsDescribedByItsLabelItsShapeAndItsValue(): void
    {
        $describer = new FaceDescriber(Dice::customDice());

        self::assertEquals([
            new FaceDescription('heads', 'Heads', 'Heads: a circle, worth 1', 1, 'circle'),
            new FaceDescription('tails', 'Tails', 'Tails: a square, worth 0', 0, 'square'),
        ], $describer->describe('coin'));
        self::assertEquals([
            new FaceDescription('ruby', 'gem.ruby', 'gem.ruby: a diamond', null, 'diamond', '#aa0000'),
            new FaceDescription('pearl', 'Pearl', 'Pearl', image: 'pearl.svg'),
            new FaceDescription('void', 'Void', 'Void'),
        ], $describer->describe('gem'));
        self::assertSame(['coin' => ['heads' => 'Heads', 'tails' => 'Tails'], 'gem' => ['ruby' => 'gem.ruby', 'pearl' => 'Pearl', 'void' => 'Void']], $describer->labels());
    }

    public function testTheWordsAndTheLabelsAreTranslated(): void
    {
        $describer = new FaceDescriber(Dice::customDice(), new Words(Dice::translator()));

        self::assertSame(['Pile : un cercle, vaut 1', 'Face : un carré, vaut 0'], array_map(static fn (FaceDescription $face): string => $face->description, $describer->describe('coin')));
        self::assertSame('Rubis : un losange', $describer->describe('gem')[0]->description);
        self::assertSame('Ruby: a diamond', $describer->describe('gem', 'en')[0]->description);
        self::assertSame('Ruby', $describer->labels('en')['gem']['ruby']);
        self::assertSame('Pile', $describer->labels()['coin']['heads']);
    }

    public function testAnUnknownDieIsRefused(): void
    {
        $this->expectException(CannotRoll::class);

        new FaceDescriber(Dice::customDice())->describe('wand');
    }

    public function testTheEnglishWordsAreThoseOfTheTranslationFile(): void
    {
        $file = (string) file_get_contents(\dirname(__DIR__, 3).'/translations/tilttek_accessible_dice.en.yaml');
        $words = new Words();

        foreach (Words::ENGLISH as $key => $english) {
            [$group, $name] = explode('.', $key);
            self::assertMatchesRegularExpression('{^'.preg_quote($group, '}').':\n(?:    .*\n)*?    '.preg_quote($name, '}').": '?".preg_quote($english, '}')."'?\n}m", $file, $key);
        }

        self::assertSame('unknown.key', $words->word('unknown.key'));
        self::assertSame('8-sided die', $words->word('die.standard', ['%sides%' => 8]));
        self::assertSame('Heads', $words->label('Heads'));
    }
}
