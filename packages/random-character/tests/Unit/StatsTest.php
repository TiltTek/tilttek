<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Internal\Check;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

#[CoversClass(Stats::class)]
#[CoversClass(StatMethod::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidRules::class)]
final class StatsTest extends TestCase
{
    public function testARolledMethodKeepsTheCanonicalRoll(): void
    {
        $stats = new Stats(StatMethod::Each, ['str', 'dex'], '4D6KH3');

        self::assertSame('4d6kh3', $stats->roll);
        self::assertSame(['str', 'dex'], $stats->attributes);
        self::assertSame([], $stats->values);
        self::assertSame(StatMethod::Each, $stats->method);
        self::assertSame('4d6kh3', new DiceRoller()->write($stats->pool()));
    }

    public function testAFixedArrayHasNoRoll(): void
    {
        $stats = new Stats(StatMethod::Array, ['str', 'dex'], values: [15, -1_000_000]);

        self::assertNull($stats->roll);
        self::assertSame([15, -1_000_000], $stats->values);

        $this->expectExceptionObject(InvalidRules::because('The stats of a fixed array have no roll.'));
        $stats->pool();
    }

    public function testTheLargestValuesAreTaken(): void
    {
        self::assertSame([1_000_000], new Stats(StatMethod::Array, ['str'], values: [1_000_000])->values);
        self::assertCount(Stats::MAX_ATTRIBUTES, new Stats(StatMethod::Each, array_map(static fn (int $i): string => 'a'.$i, range(1, Stats::MAX_ATTRIBUTES)), '1d6')->attributes);
    }

    /**
     * @return iterable<string, array{StatMethod, list<string>, ?string, list<int>, non-empty-string}>
     */
    public static function refused(): iterable
    {
        yield 'no attribute' => [StatMethod::Each, [], '1d6', [], 'The stats fill 1 to 32 attributes.'];
        yield 'too many attributes' => [StatMethod::Each, array_map(static fn (int $i): string => 'a'.$i, range(1, 33)), '1d6', [], 'The stats fill 1 to 32 attributes.'];
        yield 'a wrong key' => [StatMethod::Each, ['Str'], '1d6', [], 'The key of an attribute "Str" must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.'];
        yield 'an attribute twice' => [StatMethod::Each, ['str', 'str'], '1d6', [], 'The stats name each attribute once.'];
        yield 'each without roll' => [StatMethod::Each, ['str'], null, [], 'The stats rolled by the method "each" have a roll and no fixed values.'];
        yield 'rolled with values' => [StatMethod::RolledArray, ['str'], '1d6', [3], 'The stats rolled by the method "rolled-array" have a roll and no fixed values.'];
        yield 'a wrong roll' => [StatMethod::Each, ['str'], '1d', [], 'The roll "1d" of the stats cannot be read: '];
        yield 'array with roll' => [StatMethod::Array, ['str'], '1d6', [3], 'The stats of a fixed array have no roll, and one value per attribute.'];
        yield 'array too short' => [StatMethod::Array, ['str', 'dex'], null, [3], 'The stats of a fixed array have no roll, and one value per attribute.'];
        yield 'array too long' => [StatMethod::Array, ['str'], null, [3, 4], 'The stats of a fixed array have no roll, and one value per attribute.'];
        yield 'a value too high' => [StatMethod::Array, ['str'], null, [1_000_001], 'A value of the stats lies from -1000000 to 1000000.'];
        yield 'a value too low' => [StatMethod::Array, ['str'], null, [-1_000_001], 'A value of the stats lies from -1000000 to 1000000.'];
    }

    /**
     * @param list<string>     $attributes
     * @param list<int>        $values
     * @param non-empty-string $message
     */
    #[DataProvider('refused')]
    public function testRefusesWrongStats(StatMethod $method, array $attributes, ?string $roll, array $values, string $message): void
    {
        try {
            new Stats($method, $attributes, $roll, $values);
            self::fail('The stats are refused.');
        } catch (InvalidRules $invalidRules) {
            self::assertStringStartsWith($message, $invalidRules->getMessage());
        }
    }
}
