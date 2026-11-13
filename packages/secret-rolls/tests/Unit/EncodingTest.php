<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Instant;

#[CoversClass(Encoding::class)]
#[CoversClass(Instant::class)]
final class EncodingTest extends TestCase
{
    public function testTheHashIsTheSha256OfTheCanonicalJson(): void
    {
        self::assertSame('["a/b","é",1.0,null]', Encoding::json(['a/b', 'é', 1.0, null]));
        self::assertSame(hash('sha256', '["a/b","é",1.0,null]'), Encoding::hash(['a/b', 'é', 1.0, null]));
    }

    public function testARollIdentifierIsStableAndShort(): void
    {
        $id = Encoding::rollId('cave', 3);

        self::assertSame(substr(hash('sha256', '["tilttek-secret-rolls/1","cave",3]'), 0, 32), $id);
        self::assertNotSame($id, Encoding::rollId('cave', 4));
        self::assertNotSame($id, Encoding::rollId('forest', 3));
    }

    public function testASceneIsCheckedAndKept(): void
    {
        self::assertSame('table-1:scene_2.a', Encoding::scene('table-1:scene_2.a'));
        self::assertSame(str_repeat('s', Encoding::MAX_SCENE), Encoding::scene(str_repeat('s', Encoding::MAX_SCENE)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badScenes(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('s', Encoding::MAX_SCENE + 1)];
        yield 'a space' => ['the cave'];
        yield 'a slash' => ['a/b'];
        yield 'a new line at the end' => ["cave\n"];
    }

    #[DataProvider('badScenes')]
    public function testABadSceneIsRefused(string $scene): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains('A scene is 1 to 128 letters');

        Encoding::scene($scene);
    }

    public function testTextIsNullOrBoundedUtf8WithoutControlCharacters(): void
    {
        self::assertNull(Encoding::text(null, 'context', 4));
        self::assertSame('éé', Encoding::text('éé', 'context', 4));

        foreach (['', 'abcde', "a\tb", "\xff", 'ééé', "ab\n"] as $bad) {
            try {
                Encoding::text($bad, 'context', 4);
                self::fail(\sprintf('"%s" was taken.', $bad));
            } catch (InvalidSecretRoll $invalid) {
                self::assertSame('A context is 1 to 4 bytes of UTF-8 text without a control character.', $invalid->getMessage());
            }
        }
    }

    public function testHexadecimalIsSixtyFourLowerCaseDigits(): void
    {
        $hex = str_repeat('0a', 32);
        self::assertSame($hex, Encoding::hex64($hex, 'seed'));

        foreach ([strtoupper($hex), substr($hex, 1), $hex.'0', $hex."\n", str_repeat('g', 64)] as $bad) {
            try {
                Encoding::hex64($bad, 'seed');
                self::fail('A bad hexadecimal value was taken.');
            } catch (InvalidSecretRoll $invalid) {
                self::assertSame('A seed is 64 lower-case hexadecimal digits.', $invalid->getMessage());
            }
        }
    }

    public function testATimeIsWrittenInUtcToTheMicrosecond(): void
    {
        $at = new DateTimeImmutable('2026-11-12 15:15:03.25', new DateTimeZone('America/Toronto'));

        self::assertSame('2026-11-12T20:15:03.250000Z', Instant::write($at));
        self::assertEquals($at, Instant::read('2026-11-12T20:15:03.250000Z'));
        self::assertSame('UTC', Instant::read('2026-11-12T20:15:03.250000Z')->getTimezone()->getName());
    }

    public function testATimeWrittenAnotherWayIsRefused(): void
    {
        foreach (['2026-11-12T20:15:03Z', '2026-11-12 20:15:03.250000', '2026-13-12T20:15:03.250000Z', 'x2026-11-12T20:15:03.250000Z'] as $bad) {
            try {
                Instant::read($bad);
                self::fail(\sprintf('"%s" was read.', $bad));
            } catch (InvalidSecretRoll $invalid) {
                self::assertStringStartsWith('A time is written in UTC', $invalid->getMessage());
            }
        }
    }
}
