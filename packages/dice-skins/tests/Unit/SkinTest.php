<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Material;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinLook;
use Tilttek\DiceSkins\TextureReference;

#[CoversClass(Skin::class)]
#[CoversClass(SkinLook::class)]
final class SkinTest extends TestCase
{
    public function testASkinGivesEachTypeOfDiceItsLook(): void
    {
        $look = self::look('#9b1b30', '#ffffff', Material::Pearl, 'pearl/soft');
        $d20 = self::look('#1b1b1f', '#f5f5f5');
        $skin = new Skin('ember', 'Ember', $look, [20 => $d20]);

        self::assertSame($look, $skin->lookFor(6));
        self::assertSame($d20, $skin->lookFor(20));
        self::assertSame([
            'key' => 'ember',
            'label' => 'Ember',
            'face' => '#9b1b30',
            'ink' => '#ffffff',
            'material' => 'pearl',
            'texture' => 'pearl/soft',
            'types' => ['20' => ['face' => '#1b1b1f', 'ink' => '#f5f5f5', 'material' => 'solid']],
        ], $skin->toArray());
        self::assertSame(['key' => 'plain', 'label' => 'Plain', 'face' => '#ffffff', 'ink' => '#000000', 'material' => 'solid'], new Skin('plain', 'Plain', self::look('#fff', '#000'))->toArray());
    }

    public function testALookMeasuresItsContrast(): void
    {
        $look = self::look('#767676', '#ffffff');

        self::assertEqualsWithDelta(4.542224959605, $look->contrast(), 1e-9);
        self::assertTrue($look->reaches(Color::AA));
        self::assertFalse($look->reaches(Color::AAA));
        self::assertTrue(self::look('#000', '#fff')->reaches(21.0));
        self::assertSame(Material::Solid, $look->material);
        self::assertNull($look->texture);
    }

    public function testTheKeysAndLabelsAtTheirLimitsAreTaken(): void
    {
        $key = 'a'.str_repeat('-', 62).'b';
        $skin = new Skin($key, str_repeat('é', 80), self::look('#fff', '#000'));

        self::assertSame($key, $skin->key);
        self::assertSame('0', new Skin('0', 'Zero', self::look('#fff', '#000'))->key);
        self::assertCount(32, new Skin('wide', 'Wide', self::look('#fff', '#000'), $this->types(32))->types);
        self::assertSame(1000, array_key_last(new Skin('big', 'Big', self::look('#fff', '#000'), [2 => self::look('#fff', '#000'), 1000 => self::look('#fff', '#000')])->types));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Ember'];
        yield 'a leading dash' => ['-ember'];
        yield 'a trailing dash' => ['ember-'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a new line after' => ["ember\n"];
        yield 'an underscore' => ['ember_red'];
    }

    #[DataProvider('notKeys')]
    public function testAKeyIsASlug(string $key): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('The key of a skin is 1 to 64 characters among a-z, 0-9 and inner "-"; "'.mb_substr($key, 0, 32).'" is not.');

        new Skin($key, 'Ember', self::look('#fff', '#000'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLabels(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'too long' => [str_repeat('é', 81)];
        yield 'a control character' => ["Ember\u{7}"];
        yield 'a new line' => ["Ember\nRed"];
        yield 'not UTF-8' => ["Emb\xC3\x28r"];
    }

    #[DataProvider('notLabels')]
    public function testALabelIsShortPlainText(string $label): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('The label of the skin "ember" is 1 to 80 characters in UTF-8, without control characters.');

        new Skin('ember', $label, self::look('#fff', '#000'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function notTypes(): iterable
    {
        yield 'a key that is not a number of sides' => [['d6' => self::look('#fff', '#000')]];
        yield 'a look that is not one' => [[6 => '#fff']];
    }

    /**
     * @param array<array-key, mixed> $types
     */
    #[DataProvider('notTypes')]
    public function testTheTypesAreLooksByNumberOfSides(array $types): void
    {
        $this->expectExceptionObject(InvalidSkin::because('The types of the skin "ember" are looks by number of sides.'));

        new Skin('ember', 'Ember', self::look('#fff', '#000'), $types);
    }

    public function testASkinKeepsItsNumbersReadable(): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('The numbers of its dice of the skin "fog", #ffffff on #777777, stand out at 4.48:1; a skin keeps them at 4.5:1 at least.');

        new Skin('fog', 'Fog', self::look('#777777', '#ffffff'));
    }

    public function testEachTypeKeepsItsNumbersReadable(): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('The numbers of its d20 of the skin "fog", #ffffff on #777777, stand out at 4.48:1;');

        new Skin('fog', 'Fog', self::look('#000', '#fff'), [20 => self::look('#777777', '#ffffff')]);
    }

    public function testATypeIsADie(): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('A die has 2 to 1000 sides; not 1.');

        new Skin('odd', 'Odd', self::look('#000', '#fff'), [1 => self::look('#000', '#fff')]);
    }

    public function testASkinGivesThirtyTwoTypesTheirOwnLookAtMost(): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('The skin "wide" gives 33 types of dice their own look; it gives 32 at most.');

        new Skin('wide', 'Wide', self::look('#fff', '#000'), $this->types(33));
    }

    public function testTheSidesOfADie(): void
    {
        self::assertSame(2, Skin::sides(2));
        self::assertSame(1000, Skin::sides(1000));

        $this->expectExceptionMessageIsOrContains('A die has 2 to 1000 sides; not 1001.');
        Skin::sides(1001);
    }

    public static function look(string $face, string $ink, Material $material = Material::Solid, ?string $texture = null): SkinLook
    {
        return new SkinLook(Color::fromHex($face), Color::fromHex($ink), $material, null === $texture ? null : new TextureReference($texture));
    }

    /**
     * @return array<int, SkinLook>
     */
    private function types(int $count): array
    {
        $types = [];

        for ($sides = 2; $sides < $count + 2; ++$sides) {
            $types[$sides] = self::look('#fff', '#000');
        }

        return $types;
    }
}
