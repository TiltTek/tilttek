<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Material;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinDefinitions;

#[CoversClass(SkinDefinitions::class)]
#[CoversClass(BaseSkins::class)]
final class SkinDefinitionsTest extends TestCase
{
    public function testItReadsSkinsAndTheLooksOfTheirTypes(): void
    {
        $skins = SkinDefinitions::fromJson(<<<'JSON'
            [
                {"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#fff", "material": "pearl", "texture": "pearl/soft",
                 "types": {"20": {"face": "#1b1b1f", "ink": "#f5f5f5"}, "4": {"material": "metal", "texture": "metal/brushed"}, "8": {}}},
                {"key": "plain", "label": "Plain", "face": "#ffffff", "ink": "#000000"}
            ]
            JSON);

        self::assertSame(['ember', 'plain'], array_map(static fn (Skin $skin): string => $skin->key, $skins));
        [$ember, $plain] = $skins;
        self::assertSame(['face' => '#9b1b30', 'ink' => '#ffffff', 'material' => 'pearl', 'texture' => 'pearl/soft'], $ember->lookFor(6)->toArray());
        self::assertSame(['face' => '#1b1b1f', 'ink' => '#f5f5f5', 'material' => 'pearl', 'texture' => 'pearl/soft'], $ember->lookFor(20)->toArray());
        self::assertSame(['face' => '#9b1b30', 'ink' => '#ffffff', 'material' => 'metal', 'texture' => 'metal/brushed'], $ember->lookFor(4)->toArray());
        self::assertSame($ember->look->toArray(), $ember->lookFor(8)->toArray());
        self::assertSame(Material::Solid, $plain->look->material);
        self::assertNull($plain->look->texture);
        self::assertSame([], $plain->types);
    }

    public function testWhatASkinWritesItReadsBack(): void
    {
        $skins = BaseSkins::all();
        $written = array_map(static fn (Skin $skin): array => $skin->toArray(), $skins);

        self::assertEquals($skins, SkinDefinitions::fromArray($written));
        self::assertEquals($skins, SkinDefinitions::fromJson(json_encode($written, \JSON_THROW_ON_ERROR)));
        self::assertSame([], SkinDefinitions::fromJson('[]'));
    }

    public function testTheBaseSkinsAreReadableAndIvoryComesFirst(): void
    {
        $skins = BaseSkins::all();

        self::assertSame(['ivory', 'obsidian', 'crimson', 'forest', 'ocean', 'amethyst', 'gold', 'high-contrast'], array_map(static fn (Skin $skin): string => $skin->key, $skins));
        self::assertSame(BaseSkins::DEFAULT, $skins[0]->key);
        self::assertSame(['key' => 'high-contrast', 'label' => 'High contrast', 'face' => '#ffffff', 'ink' => '#000000', 'material' => 'solid'], BaseSkins::highContrast()->toArray());
        self::assertEquals(BaseSkins::highContrast(), $skins[7]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['[', 'The skin definitions are not JSON of 8 levels at most: Syntax error'];
        yield 'too deep' => ['[[[[[[[[]]]]]]]]', 'The skin definitions are not JSON of 8 levels at most: Maximum stack depth exceeded'];
        yield 'a number' => ['12', 'The skin definitions are a list of skins.'];
        yield 'an object' => ['{"key": "ember"}', 'The skin definitions are a list of skins.'];
        yield 'not a skin' => ['["ember"]', 'A skin is an object of fields.'];
        yield 'an unknown field' => ['[{"key": "ember", "colour": "#fff"}]', 'The field "colour" of a skin is not one of key, label, face, ink, material, texture, types.'];
        yield 'no key' => ['[{"label": "Ember"}]', 'The field "key" of a skin is a text.'];
        yield 'a key not a text' => ['[{"key": 12}]', 'The field "key" of a skin is a text.'];
        yield 'no face' => ['[{"key": "ember", "label": "Ember", "ink": "#fff"}]', 'The field "face" of the skin "ember" is a text.'];
        yield 'no ink' => ['[{"key": "ember", "label": "Ember", "face": "#000"}]', 'The field "ink" of the skin "ember" is a text.'];
        yield 'no label' => ['[{"key": "ember", "face": "#000", "ink": "#fff"}]', 'The field "label" of the skin "ember" is a text.'];
        yield 'a material not known' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "material": "resin"}]', 'The material of the skin "ember" is one of solid, translucent, pearl, glitter, marble, metal, wood, stone, gem.'];
        yield 'a material not a text' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "material": 3}]', 'The field "material" of the skin "ember" is a text.'];
        yield 'a texture not a text' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "texture": true}]', 'The field "texture" of the skin "ember" is a text.'];
        yield 'types not an object' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": "d20"}]', 'The types of the skin "ember" are an object of looks by number of sides.'];
        yield 'a type not named by its sides' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"d20": {}}}]', 'A type of dice of the skin "ember" is named by its number of sides; "d20" is not one.'];
        yield 'a type of leading zero' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"020": {}}}]', 'A type of dice of the skin "ember" is named by its number of sides; "020" is not one.'];
        yield 'a type beyond the integers' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"99999999999999999999": {}}}]', 'is named by its number of sides; "9999999999999999" is not one.'];
        yield 'a type of one side' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"1": {}}}]', 'A die has 2 to 1000 sides; not 1.'];
        yield 'a type of too many sides' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"1001": {}}}]', 'A die has 2 to 1000 sides; not 1001.'];
        yield 'a type not an object' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": "red"}}]', 'The look of the d20 of the skin "ember" is an object of fields.'];
        yield 'a type of unknown field' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"label": "x"}}}]', 'The field "label" of the d20 of the skin "ember" is not one of face, ink, material, texture.'];
        yield 'a type face not a text' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"face": 1}}}]', 'The field "face" of the d20 of the skin "ember" is a text.'];
        yield 'a type ink not a text' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"ink": 1}}}]', 'The field "ink" of the d20 of the skin "ember" is a text.'];
        yield 'a type of bad material' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"material": "cheese"}}}]', 'The material of the d20 of the skin "ember" is one of'];
        yield 'a type of bad texture' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"texture": "../x"}}}]', 'A texture is a key of 1 to 128 characters'];
        yield 'a type unreadable' => ['[{"key": "ember", "label": "Ember", "face": "#000", "ink": "#fff", "types": {"20": {"ink": "#111"}}}]', 'The numbers of its d20 of the skin "ember"'];
        yield 'a bad colour' => ['[{"key": "ember", "label": "Ember", "face": "black", "ink": "#fff"}]', 'A colour is written #rgb or #rrggbb; "black" is not.'];
        yield 'two skins of a key' => ['[{"key": "a", "label": "A", "face": "#000", "ink": "#fff"}, {"key": "a", "label": "B", "face": "#000", "ink": "#fff"}]', 'Two skins have the key "a".'];
        yield 'a long key quoted short' => ['[{"key": "'.str_repeat('k', 40).'", "label": "A"}]', 'The field "face" of the skin "'.str_repeat('k', 32).'" is a text.'];
    }

    #[DataProvider('refusedJson')]
    public function testWhatIsNotAListOfSkinsIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains($message);

        SkinDefinitions::fromJson($json);
    }

    public function testEightLevelsAreRead(): void
    {
        self::assertCount(1, SkinDefinitions::fromJson('[{"key": "a", "label": "A", "face": "#000", "ink": "#fff", "types": {"20": {"face": "#111"}}}]'));

        // The deepest text json_decode() takes at a depth of 8 is decoded, then refused as a skin.
        $this->expectExceptionMessageIsOrContains('The field "0" of a skin is not one of');
        SkinDefinitions::fromJson('[[[[[[[]]]]]]]');
    }

    public function testAListIsExpected(): void
    {
        $this->expectExceptionMessageIsOrContains('The skin definitions are a list of skins.');

        SkinDefinitions::fromArray([1 => ['key' => 'a']]);
    }

    public function testTheTextIsBounded(): void
    {
        $skin = ['key' => 'a', 'label' => 'A', 'face' => '#000', 'ink' => '#fff'];
        $json = json_encode([$skin], \JSON_THROW_ON_ERROR);
        $padded = str_pad($json, SkinDefinitions::MAX_JSON_BYTES, ' ');

        self::assertCount(1, SkinDefinitions::fromJson($padded));

        $this->expectExceptionMessageIsOrContains('A text of skin definitions holds 262144 bytes at most.');
        SkinDefinitions::fromJson($padded.' ');
    }

    public function testASiteOffersTwoHundredFiftySixSkinsAtMost(): void
    {
        $skins = [];

        for ($index = 0; $index < SkinDefinitions::MAX_SKINS; ++$index) {
            $skins[] = ['key' => 's'.$index, 'label' => 'S', 'face' => '#000', 'ink' => '#fff'];
        }

        self::assertCount(256, SkinDefinitions::fromArray($skins));

        $skins[] = ['key' => 'one-more', 'label' => 'S', 'face' => '#000', 'ink' => '#fff'];
        $this->expectExceptionMessageIsOrContains('A site offers 256 skins at most; the definitions hold 257.');
        SkinDefinitions::fromArray($skins);
    }

    public function testAnIntegerTypeKeyIsRead(): void
    {
        $skins = SkinDefinitions::fromArray([['key' => 'a', 'label' => 'A', 'face' => '#000', 'ink' => '#fff', 'types' => [12 => ['face' => '#222']]]]);

        self::assertSame('#222222', $skins[0]->lookFor(12)->face->hex);
    }
}
