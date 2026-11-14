<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Material;
use Tilttek\DiceSkins\TextureReference;

#[CoversClass(Material::class)]
#[CoversClass(TextureReference::class)]
final class MaterialAndTextureTest extends TestCase
{
    public function testAFreeTextNamesAMaterialOrFallsBackToSolid(): void
    {
        self::assertSame(Material::Metal, Material::fromText(' Metal '));
        self::assertSame(Material::Gem, Material::fromText('gem'));
        self::assertSame(Material::Solid, Material::fromText('Resin'));
        self::assertSame(Material::Solid, Material::fromText('plastic'));
        self::assertSame(Material::Solid, Material::fromText('acrylic'));
        self::assertSame(Material::Translucent, Material::fromText('GLASS'));
        self::assertSame(Material::Gem, Material::fromText('crystal'));
        self::assertSame(Material::Stone, Material::fromText('bone'));
        self::assertSame(Material::Solid, Material::fromText('cheese'));
        self::assertSame(Material::Solid, Material::fromText(null));
    }

    public function testATextureIsAKey(): void
    {
        self::assertSame('marble/veins-02', new TextureReference('marble/veins-02')->key);
        self::assertSame('a', new TextureReference('a')->key);
        self::assertSame('9.png', new TextureReference('9.png')->key);
        self::assertSame(str_repeat('a', 128), new TextureReference(str_repeat('a', 128))->key);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'an address' => ['https://example.com/a.png'];
        yield 'upper case' => ['Marble'];
        yield 'a leading slash' => ['/marble'];
        yield 'a leading dot' => ['.marble'];
        yield 'a parent' => ['marble/../secret'];
        yield 'two slashes' => ['marble//veins'];
        yield 'a trailing slash' => ['marble/'];
        yield 'a new line after' => ["marble\n"];
        yield 'a space' => ['marble veins'];
    }

    #[DataProvider('notKeys')]
    public function testAnythingElseIsRefused(string $key): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('A texture is a key of 1 to 128 characters among a-z, 0-9, ".", "_", "-" and "/", never an address; "'.mb_substr($key, 0, 32).'" is not.');

        new TextureReference($key);
    }
}
