<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Material;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinDefinitions;
use Tilttek\DiceSkins\SkinLook;
use Tilttek\DiceSkins\TextureReference;

/**
 * What a refusal quotes of the text it refuses: its first characters — not bytes — so that a long or a multibyte text
 * never fills a log nor splits a character in two.
 */
#[CoversClass(Color::class)]
#[CoversClass(Skin::class)]
#[CoversClass(SkinDefinitions::class)]
#[CoversClass(TextureReference::class)]
final class ReadableMessagesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: Closure(string): mixed, 1: string, 2?: int}>
     */
    public static function quoted(): iterable
    {
        $look = new SkinLook(Color::white(), Color::black(), Material::Solid);

        yield 'a colour' => [Color::fromHex(...), 'A colour is written #rgb or #rrggbb; "%s" is not.', 16];
        yield 'a key' => [static fn (string $text): Skin => new Skin($text, 'Label', $look), 'The key of a skin is 1 to 64 characters among a-z, 0-9 and inner "-"; "%s" is not.'];
        yield 'a texture' => [static fn (string $text): TextureReference => new TextureReference($text), 'A texture is a key of 1 to 128 characters among a-z, 0-9, ".", "_", "-" and "/", never an address; "%s" is not.'];
        yield 'a field of the skin' => [static fn (string $text): array => SkinDefinitions::fromArray([[$text => 1]]), 'The field "%s" of a skin is not one of key, label, face, ink, material, texture, types.'];
        yield 'a type of dice' => [static fn (string $text): array => SkinDefinitions::fromArray([['key' => 'a', 'label' => 'L', 'face' => '#fff', 'ink' => '#000', 'types' => [$text => []]]]), 'A type of dice of the skin "a" is named by its number of sides; "%s" is not one.', 16];
        yield 'a field of a look' => [static fn (string $text): array => SkinDefinitions::fromArray([['key' => 'a', 'label' => 'L', 'face' => '#fff', 'ink' => '#000', 'types' => [4 => [$text => 1]]]]), 'The field "%s" of the d4 of the skin "a" is not one of face, ink, material, texture.'];
        yield 'the key in a message' => [static fn (string $text): array => SkinDefinitions::fromArray([['key' => $text, 'label' => 'L', 'face' => '#fff']]), 'The field "ink" of the skin "%s" is a text.'];
    }

    /**
     * @param Closure(string): mixed $read
     */
    #[DataProvider('quoted')]
    public function testALongTextIsQuotedByItsFirstCharacters(Closure $read, string $message, int $length = 32): void
    {
        $text = str_repeat('é', 40);

        try {
            $read($text);
            self::fail('The text should have been refused.');
        } catch (InvalidSkin $invalidSkin) {
            self::assertStringContainsString(\sprintf($message, str_repeat('é', $length)), $invalidSkin->getMessage());
            self::assertTrue(mb_check_encoding($invalidSkin->getMessage(), 'UTF-8'));
        }
    }

    public function testAnAsciiFieldIsQuotedOnThirtyTwoCharacters(): void
    {
        try {
            SkinDefinitions::fromArray([[str_repeat('x', 40) => 1]]);
            self::fail('The field should have been refused.');
        } catch (InvalidSkin $invalidSkin) {
            self::assertStringContainsString('The field "'.str_repeat('x', 32).'" of a skin', $invalidSkin->getMessage());
        }
    }

    public function testALabelMayHoldCharactersWhoseBytesLookLikeControls(): void
    {
        // U+0100 is written C4 80 and U+0080 is a control character: only the second one is refused.
        self::assertSame('Ā', new Skin('plain', 'Ā', new SkinLook(Color::white(), Color::black(), Material::Solid))->label);

        $this->expectException(InvalidSkin::class);
        new Skin('plain', "A\u{0080}", new SkinLook(Color::white(), Color::black(), Material::Solid));
    }
}
