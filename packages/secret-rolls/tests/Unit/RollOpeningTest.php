<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Encoding;
use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\RollCommitment;
use Tilttek\SecretRolls\RollOpening;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(RollOpening::class)]
#[CoversClass(RollCommitment::class)]
final class RollOpeningTest extends TestCase
{
    public function testAnOpeningWritesAndReadsBack(): void
    {
        $opening = new Table()->rolls->roll('cave', '2d6+1', context: 'Trap')->opening;
        $data = $opening->toArray();

        self::assertSame(RollOpening::FIELDS, array_keys($data));
        self::assertEquals($opening, RollOpening::fromArray($data));
        $decoded = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertEquals($opening, RollOpening::fromArray($decoded));
        self::assertSame(array_column($opening->result, 'total'), $opening->totals());
    }

    public function testTheCommitmentHashesEveryFieldInOrder(): void
    {
        $opening = new Table()->rolls->roll('cave', '1d6', context: 'Trap')->opening;
        $expected = hash('sha256', Encoding::json(['tilttek-secret-rolls/1', ...array_values($opening->toArray())]));

        self::assertSame($expected, $opening->commitment()->hex);
        self::assertTrue($opening->commitment()->equals(RollCommitment::fromHex($expected)));
        self::assertFalse($opening->commitment()->equals(RollCommitment::fromHex(str_repeat('0', 64))));

        $data = $opening->toArray();
        $data['context'] = 'Another trap';
        self::assertNotSame($expected, RollOpening::fromArray($data)->commitment()->hex);
    }

    public function testACommitmentIsSixtyFourHexadecimalDigits(): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains('A commitment is 64 lower-case hexadecimal digits.');

        RollCommitment::fromHex('abc');
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badOpenings(): iterable
    {
        $good = ['roll' => str_repeat('a', 32), 'scene' => 'cave', 'position' => 1, 'expression' => '1d6', 'rounding' => 'NegativeInfinity', 'seed' => str_repeat('b', 64), 'result' => [['total' => 3]], 'context' => null];

        yield 'a field missing' => [array_diff_key($good, ['context' => true]), 'An opening holds'];
        yield 'a field more' => [[...$good, 'note' => 'x'], 'An opening holds'];
        yield 'fields out of order' => [['scene' => 'cave', ...$good], 'An opening holds'];
        yield 'a short roll' => [array_replace($good, ['roll' => 'abc']), 'The roll of an opening'];
        yield 'a roll with a prefix' => [array_replace($good, ['roll' => 'zz'.str_repeat('a', 32)]), 'The roll of an opening'];
        yield 'a roll too long' => [array_replace($good, ['roll' => str_repeat('a', 33)]), 'The roll of an opening'];
        yield 'a roll with a final newline' => [array_replace($good, ['roll' => str_repeat('a', 32)."\n"]), 'The roll of an opening'];
        yield 'a roll of numbers' => [array_replace($good, ['roll' => 12]), 'The roll of an opening'];
        yield 'an upper-case roll' => [array_replace($good, ['roll' => str_repeat('A', 32)]), 'The roll of an opening'];
        yield 'position 0' => [array_replace($good, ['position' => 0]), 'The position of an opening'];
        yield 'a position in text' => [array_replace($good, ['position' => '1']), 'The position of an opening'];
        yield 'an empty expression' => [array_replace($good, ['expression' => '']), 'The expression of an opening is 1 to 4096 bytes.'];
        yield 'a long expression' => [array_replace($good, ['expression' => str_repeat('1', RollOpening::MAX_EXPRESSION + 1)]), 'The expression of an opening'];
        yield 'an expression of numbers' => [array_replace($good, ['expression' => 6]), 'The expression of an opening'];
        yield 'a rounding of numbers' => [array_replace($good, ['rounding' => 1]), 'are text'];
        yield 'a seed of numbers' => [array_replace($good, ['seed' => 1]), 'are text'];
        yield 'a scene of numbers' => [array_replace($good, ['scene' => 1]), 'are text'];
        yield 'a context of numbers' => [array_replace($good, ['context' => 1]), 'are text'];
        yield 'a result of text' => [array_replace($good, ['result' => '3']), 'The result of an opening'];
        yield 'an empty result' => [array_replace($good, ['result' => []]), 'The result of an opening'];
        yield 'a result by key' => [array_replace($good, ['result' => ['a' => ['total' => 3]]]), 'The result of an opening'];
        yield 'a result of numbers' => [array_replace($good, ['result' => [['total' => 3], 4]]), 'The result of an opening'];
        yield 'a bad scene' => [array_replace($good, ['scene' => 'the cave']), 'A scene is'];
        yield 'a bad seed' => [array_replace($good, ['seed' => 'abc']), 'A seed is'];
        yield 'a bad context' => [array_replace($good, ['context' => '']), 'A context is'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('badOpenings')]
    public function testAnOpeningThatDoesNotReadIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidSecretRoll::class);
        $this->expectExceptionMessageIsOrContains($message);

        RollOpening::fromArray($data);
    }

    public function testTheLongestExpressionIsTaken(): void
    {
        $data = new Table()->rolls->roll('cave', '1d6')->opening->toArray();
        $data['expression'] = str_repeat('1', RollOpening::MAX_EXPRESSION);

        self::assertSame(RollOpening::MAX_EXPRESSION, \strlen(RollOpening::fromArray($data)->expression));
    }
}
