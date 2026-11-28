<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Expression\Pool;
use Tilttek\RpgSystems\Attribute;
use Tilttek\RpgSystems\DiceExpression;
use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Term;
use Tilttek\RpgSystems\Text;
use Tilttek\RpgSystems\Translations;

#[CoversClass(Attribute::class)]
#[CoversClass(DiceExpression::class)]
#[CoversClass(Term::class)]
#[CoversClass(Text::class)]
#[CoversClass(Translations::class)]
#[CoversClass(Provenance::class)]
#[CoversClass(Licence::class)]
#[CoversClass(InvalidRecord::class)]
final class RecordsTest extends TestCase
{
    private Provenance $source;

    protected function setUp(): void
    {
        $this->source = new Provenance('Lanterns', Licence::Owned);
    }

    public function testATranslationIsFoundByLocaleThenLanguageThenFallbackThenFirst(): void
    {
        $names = Translations::of(['en' => 'Strength', 'fr' => 'Force', 'fr-ca' => 'Force (Québec)', 'de' => 'Stärke']);

        self::assertSame(['en', 'fr', 'fr_CA', 'de'], $names->locales());
        self::assertSame('Force (Québec)', $names->in('fr_CA'));
        self::assertSame('Force', $names->in('fr-BE'));
        self::assertSame('Strength', $names->in('it'));
        self::assertSame('Stärke', $names->in('it', 'de'));
        self::assertSame('Stärke', Translations::of(['de' => 'Stärke', 'es' => 'Fuerza'])->in('it'));
        self::assertNull(new Translations()->in('en'));
        self::assertTrue($names->has('FR'));
        self::assertFalse($names->has('es'));
        self::assertFalse($names->isEmpty());
        self::assertTrue(new Translations()->isEmpty());
        self::assertSame('Fuerza', $names->with('es', 'Fuerza')->in('es'));
        self::assertSame('Kraft', $names->with('de', 'Kraft')->in('de'));
        self::assertSame(['en' => 'Strength', 'fr' => 'Force', 'fr_CA' => 'Force (Québec)', 'de' => 'Stärke', 'es_419' => 'Fuerza'], $names->with('es-419', 'Fuerza')->texts);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLocales(): iterable
    {
        yield 'empty' => [''];
        yield 'too long a language' => ['engl'];
        yield 'a digit' => ['e1'];
        yield 'a long region' => ['fr_CAN'];
        yield 'three parts' => ['fr_CA_x'];
        yield 'a space' => ['fr CA'];
    }

    #[DataProvider('badLocales')]
    public function testALocaleIsALanguageAndAnOptionalRegion(string $locale): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a locale', $locale));

        Translations::of([$locale => 'x']);
    }

    public function testTextsAreBounded(): void
    {
        self::assertSame(str_repeat('é', Text::MAX_LENGTH), Text::check(str_repeat('é', Text::MAX_LENGTH), 'A name'));
        self::assertSame("Two\nlines\tand a tab", Text::check("Two\nlines\tand a tab", 'A name'));
        self::assertSame('', Text::check('', 'A name', required: false));

        foreach ([
            [str_repeat('a', Text::MAX_LENGTH + 1), 'A name is longer than 4000 characters.'],
            ['  ', 'A name is required.'],
            ["a\x00b", 'A name is not UTF-8 or holds control characters.'],
            ["a\x7Fb", 'A name is not UTF-8 or holds control characters.'],
            ["\xC3\x28", 'A name is not UTF-8 or holds control characters.'],
        ] as [$text, $message]) {
            try {
                Text::check($text, 'A name');
                self::fail('The text was accepted.');
            } catch (InvalidRecord $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testATextHoldsAtMostSixtyFourLocales(): void
    {
        $texts = [];

        foreach (range('a', 'z') as $first) {
            foreach (range('a', 'z') as $second) {
                $texts[$first.$second] = 'x';
            }
        }

        $texts = \array_slice($texts, 0, 64);

        self::assertCount(64, new Translations($texts)->texts);

        $this->expectExceptionMessageIsOrContains('The name has more than 64 locales.');

        new Translations([...$texts, 'zz' => 'x'], 'The name');
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function keys(): iterable
    {
        yield 'letters' => ['str', true];
        yield 'dots between' => ['condition.blinded', true];
        yield 'hyphen and underscore' => ['sleight-of_hand', true];
        yield 'a digit first' => ['5e', true];
        yield 'ninety-six characters' => [str_repeat('a', 96), true];
        yield 'ninety-seven characters' => [str_repeat('a', 97), false];
        yield 'empty' => ['', false];
        yield 'uppercase' => ['Str', false];
        yield 'a leading dot' => ['.str', false];
        yield 'a trailing dot' => ['str.', false];
        yield 'two dots' => ['a..b', false];
        yield 'a leading hyphen' => ['-str', false];
        yield 'a space' => ['s tr', false];
        yield 'a new line after' => ["str\n", false];
    }

    #[DataProvider('keys')]
    public function testAKeyIsLowercaseAsciiWithSingleDots(string $key, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InvalidRecord::class);
            $this->expectExceptionMessageIsOrContains(\sprintf('The key of a term "%s" must be 1 to 96', $key));
        }

        self::assertSame($key, new Term($key, Translations::of(['en' => 'x']), $this->source)->key);
    }

    public function testAnAttributeHasANameAndOrderedBounds(): void
    {
        $attribute = new Attribute('grit', Translations::of(['en' => 'Grit']), $this->source, minimum: 1, maximum: 6);

        self::assertTrue($attribute->allows(1));
        self::assertTrue($attribute->allows(6));
        self::assertFalse($attribute->allows(0));
        self::assertFalse($attribute->allows(7));
        self::assertTrue(new Attribute('glow', Translations::of(['en' => 'Glow']), $this->source, minimum: 3)->allows(\PHP_INT_MAX));
        self::assertFalse(new Attribute('glow', Translations::of(['en' => 'Glow']), $this->source, minimum: 3)->allows(2));
        self::assertTrue(new Attribute('glow', Translations::of(['en' => 'Glow']), $this->source, maximum: 3)->allows(\PHP_INT_MIN));
        self::assertTrue(new Attribute('same', Translations::of(['en' => 'Same']), $this->source, minimum: 2, maximum: 2)->allows(2));
        self::assertTrue($attribute->abbreviations->isEmpty());
        self::assertTrue($attribute->descriptions->isEmpty());

        try {
            new Attribute('grit', new Translations(), $this->source);
            self::fail('An attribute without a name was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The attribute "grit" has no name.', $invalidRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The attribute "grit" has a minimum, 7, above its maximum, 6.');

        new Attribute('grit', Translations::of(['en' => 'Grit']), $this->source, minimum: 7, maximum: 6);
    }

    public function testADiceExpressionIsCheckedAndNormalisedByTheDiceLibrary(): void
    {
        $advantage = new DiceExpression('advantage', ' 2D20KH1 ', Translations::of(['en' => 'Advantage']), $this->source);

        self::assertSame('2d20kh1', $advantage->notation);
        self::assertSame('2W20kh1', $advantage->notationIn('de'));
        self::assertInstanceOf(Pool::class, $advantage->pool());

        try {
            new DiceExpression('broken', '2d', Translations::of(['en' => 'Broken']), $this->source);
            self::fail('A broken notation was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertStringStartsWith('The notation "2d" of the dice expression "broken" cannot be read: ', $invalidRecord->getMessage());
        }

        try {
            new DiceExpression('empty', '', Translations::of(['en' => 'Empty']), $this->source);
            self::fail('An empty notation was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The notation of the dice expression "empty" is required.', $invalidRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The dice expression "check" has no name.');

        new DiceExpression('check', '1d20', new Translations(), $this->source);
    }

    public function testATermHasANameAndACategoryThatIsAKey(): void
    {
        $term = new Term('condition.shaken', Translations::of(['en' => 'Shaken']), $this->source, 'condition', Translations::of(['en' => 'Unsteady.']));

        self::assertSame('condition', $term->category);
        self::assertSame('Unsteady.', $term->descriptions->in('en'));
        self::assertNull(new Term('near', Translations::of(['en' => 'Near']), $this->source)->category);

        try {
            new Term('near', new Translations(), $this->source);
            self::fail('A term without a name was accepted.');
        } catch (InvalidRecord $invalidRecord) {
            self::assertSame('The term "near" has no name.', $invalidRecord->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The key of the category of a term "Condition"');

        new Term('shaken', Translations::of(['en' => 'Shaken']), $this->source, 'Condition');
    }

    public function testAProvenanceAsksForTheAttributionItsLicenceRequires(): void
    {
        $srd = new Provenance('SRD 5.2', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.', 'A publisher', 'https://example.org/srd');

        self::assertSame('https://example.org/srd', $srd->url?->toString());
        self::assertTrue($srd->equals(new Provenance('SRD 5.2', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.', 'A publisher', 'https://example.org/srd')));
        self::assertFalse($srd->equals(new Provenance('SRD 5.2', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.', 'A publisher')));
        self::assertFalse($srd->equals(new Provenance('SRD 5.2', Licence::CcBySa40, 'Material from the SRD 5.2, CC BY 4.0.', 'A publisher', 'https://example.org/srd')));
        self::assertFalse($srd->equals(new Provenance('SRD 5.2', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.', 'Another', 'https://example.org/srd')));
        self::assertFalse($srd->equals(new Provenance('SRD 5.1', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.', 'A publisher', 'https://example.org/srd')));
        self::assertFalse($srd->equals(new Provenance('SRD 5.2', Licence::CcBy40, 'Other words.', 'A publisher', 'https://example.org/srd')));
        self::assertNotSame(new Provenance('a', Licence::Owned, '', 'b')->identity(), new Provenance('a', Licence::Owned, 'b')->identity());
        self::assertSame('', new Provenance('Public', Licence::Cc0)->attribution);
        self::assertNull(new Provenance('Public', Licence::Cc0)->url);

        foreach ([
            static fn (): Provenance => new Provenance('SRD', Licence::CcBy40),
            static fn (): Provenance => new Provenance('SRD', Licence::Orc, ' '),
            static fn (): Provenance => new Provenance('', Licence::Owned),
            static fn (): Provenance => new Provenance('SRD', Licence::Owned, author: ''),
        ] as $make) {
            try {
                $make();
                self::fail('A provenance without its required text was accepted.');
            } catch (InvalidRecord $exception) {
                self::assertStringEndsWith('is required.', $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('The address of the source "SRD" is not an absolute http or https address: ');

        new Provenance('SRD', Licence::Owned, url: 'ftp://example.org/srd');
    }

    public function testLicencesSayWhatTheyAskFor(): void
    {
        self::assertSame(['CC-BY-4.0', 'CC-BY-3.0', 'CC-BY-SA-4.0', 'CC0-1.0', 'ORC', 'owned'], array_map(static fn (Licence $licence): string => $licence->value, Licence::cases()));
        self::assertSame([true, true, true, false, true, false], array_map(static fn (Licence $licence): bool => $licence->requiresAttribution(), Licence::cases()));
        self::assertSame([false, false, true, false, true, false], array_map(static fn (Licence $licence): bool => $licence->isReciprocal(), Licence::cases()));
        self::assertSame('Creative Commons Attribution 3.0 Unported', Licence::CcBy30->title());
        self::assertSame('https://creativecommons.org/licenses/by/3.0/', Licence::CcBy30->url());
        self::assertSame('Creative Commons Attribution 4.0 International', Licence::CcBy40->title());
        self::assertSame('Creative Commons Attribution-ShareAlike 4.0 International', Licence::CcBySa40->title());
        self::assertSame('Creative Commons Zero 1.0 Universal', Licence::Cc0->title());
        self::assertSame('ORC License', Licence::Orc->title());
        self::assertSame('All rights reserved', Licence::Owned->title());
        self::assertSame('https://creativecommons.org/licenses/by/4.0/', Licence::CcBy40->url());
        self::assertSame('https://creativecommons.org/licenses/by-sa/4.0/', Licence::CcBySa40->url());
        self::assertSame('https://creativecommons.org/publicdomain/zero/1.0/', Licence::Cc0->url());
        self::assertSame('https://paizo.com/orclicense', Licence::Orc->url());
        self::assertNull(Licence::Owned->url());
    }
}
