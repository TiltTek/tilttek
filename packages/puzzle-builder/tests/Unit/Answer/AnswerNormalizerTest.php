<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit\Answer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PuzzleBuilder\Answer\AnswerNormalizer;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;

#[CoversClass(AnswerNormalizer::class)]
#[CoversClass(InvalidAnswer::class)]
final class AnswerNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function defaults(): iterable
    {
        yield 'case' => ['MidNight', 'midnight'];
        yield 'accents' => ['Le Chêne', 'lechene'];
        yield 'punctuation and symbols' => ['¡Hé, là-bas! $5 + 2€', 'helabas52'];
        yield 'spaces of every kind' => ["  a\tb\u{00A0}c\u{2003}d\n", 'abcd'];
        yield 'full-width letters' => ['ＡＢＣ１', 'abc1'];
        yield 'invisible characters' => ["pass\u{200B}word\u{0007}", 'password'];
        yield 'case folding beyond lower case' => ['Straße', 'strasse'];
    }

    #[DataProvider('defaults')]
    public function testTheDefaultsFoldCaseAccentsPunctuationAndSpaces(string $text, string $normalized): void
    {
        self::assertSame($normalized, new AnswerNormalizer()->normalize($text));
    }

    public function testAccentsAreKeptWhenAsked(): void
    {
        $normalizer = new AnswerNormalizer(keepAccents: true);

        self::assertTrue($normalizer->keepAccents);
        self::assertSame('lechêne', $normalizer->normalize('Le Che'."\u{0302}".'ne'));
        self::assertSame('le chêne', new AnswerNormalizer(true, true)->normalize(' Le   chêne '));
    }

    public function testSpacesAreKeptAsOneWhenAsked(): void
    {
        $normalizer = new AnswerNormalizer(keepSpaces: true);

        self::assertTrue($normalizer->keepSpaces);
        self::assertSame('the old oak', $normalizer->normalize("  The \t old\u{00A0}\u{00A0}oak!  "));
    }

    public function testPunctuationIsKeptWhenAsked(): void
    {
        $normalizer = new AnswerNormalizer(keepPunctuation: true);

        self::assertTrue($normalizer->keepPunctuation);
        self::assertSame('a-b,c!', $normalizer->normalize('A-B, c!'));
        self::assertFalse(new AnswerNormalizer()->keepPunctuation);
    }

    public function testATextOfTheLongestLengthIsRead(): void
    {
        self::assertSame(str_repeat('é', 200), new AnswerNormalizer(keepAccents: true)->normalize(str_repeat('É', 200)));
    }

    public function testALongerTextIsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('An answer holds 200 characters at most.');

        new AnswerNormalizer()->normalize(str_repeat('é', 201));
    }

    public function testATextThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('An answer is UTF-8 text.');

        new AnswerNormalizer()->normalize("\xC3\x28");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function empties(): iterable
    {
        yield 'nothing' => [''];
        yield 'spaces' => ["  \t "];
        yield 'punctuation' => ['?!...'];
        yield 'an accent alone' => ["\u{0301}"];
    }

    #[DataProvider('empties')]
    public function testATextEmptyOnceNormalizedIsRefused(string $text): void
    {
        $this->expectException(InvalidAnswer::class);
        $this->expectExceptionMessageIsOrContains('The answer holds nothing once normalized.');

        new AnswerNormalizer()->normalize($text);
    }

    public function testTheResultIsComposed(): void
    {
        self::assertSame("\u{00E9}t\u{00E9}", new AnswerNormalizer(keepAccents: true)->normalize("e\u{0301}te\u{0301}"));
    }
}
