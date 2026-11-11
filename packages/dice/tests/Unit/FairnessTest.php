<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Fairness\Commitment;
use Tilttek\Dice\Fairness\FairRoller;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Fairness\VerifiableRoll;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Replay\ReplayableRoll;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Seed::class)]
#[CoversClass(Commitment::class)]
#[CoversClass(FairRoller::class)]
#[CoversClass(VerifiableRoll::class)]
#[CoversClass(Replayer::class)]
#[CoversClass(ReplayableRoll::class)]
final class FairnessTest extends TestCase
{
    private const string SEED = '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f';

    public function testASeedIsDrawnFromTheSecureSource(): void
    {
        self::assertSame('0100000002000000030000000400000005000000060000000700000008000000', Seed::generate(ScriptedEngine::randomizer(1, 2, 3, 4, 5, 6, 7, 8))->hex());
        self::assertSame(self::SEED, Seed::fromHex(self::SEED)->hex());
    }

    public function testASeedIsSixtyFourHexadecimalDigits(): void
    {
        foreach ([substr(self::SEED, 1), strtoupper(self::SEED), self::SEED.'0', substr(self::SEED, 1).'g', self::SEED."\n"] as $invalid) {
            try {
                Seed::fromHex($invalid);
                self::fail(\sprintf('"%s" was a seed.', $invalid));
            } catch (InvalidDefinition $exception) {
                self::assertSame('A seed is 64 lower-case hexadecimal digits.', $exception->getMessage());
            }
        }
    }

    public function testTheCommitmentIsTheSha256OfTheSeed(): void
    {
        $seed = Seed::fromHex(self::SEED);
        $commitment = $seed->commitment();

        self::assertSame(hash('sha256', sodium_hex2bin(self::SEED)), $commitment->hex);
        self::assertTrue($commitment->matches($seed));
        self::assertFalse($commitment->matches(Seed::fromHex(str_repeat('0', 64))));
        self::assertSame($commitment->hex, Commitment::fromHex($commitment->hex)->hex);
    }

    public function testACommitmentIsSixtyFourHexadecimalDigits(): void
    {
        foreach ([str_repeat('A', 64), 'z'.str_repeat('a', 64), str_repeat('a', 65), str_repeat('a', 64)."\n"] as $invalid) {
            try {
                Commitment::fromHex($invalid);
                self::fail(\sprintf('"%s" was a commitment.', $invalid));
            } catch (InvalidDefinition $exception) {
                self::assertSame('A commitment is 64 lower-case hexadecimal digits.', $exception->getMessage());
            }
        }
    }

    public function testTheRandomnessIsAnHmacOfTheClientSeedAndTheNonceUnderTheSeed(): void
    {
        $seed = Seed::fromHex(self::SEED);
        $expected = new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', "player\n3", sodium_hex2bin(self::SEED), true)));
        $plain = new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', "\n0", sodium_hex2bin(self::SEED), true)));

        self::assertSame($expected->getBytes(32), $seed->randomizer('player', 3)->getBytes(32));
        self::assertSame($plain->getBytes(32), $seed->randomizer()->getBytes(32));
    }

    public function testAVerifiableRollIsCheckedByAnyone(): void
    {
        $pool = new DiceParser()->parse('4d6kh3, 1d20');
        $seed = Seed::fromHex(self::SEED);
        $fair = new FairRoller();
        $roll = $fair->roll($pool, $seed, 'player', 7);
        $published = $roll->toArray();

        self::assertSame([$seed->commitment()->hex, self::SEED, 'player', 7, '4d6kh3, 1d20'], [$published['commitment'], $published['seed'], $published['client_seed'], $published['nonce'], $published['expression']]);
        self::assertSame($roll->result->toArray(), $published['result']);
        self::assertSame([$seed, 'player', 7], [$roll->seed, $roll->clientSeed, $roll->nonce]);
        self::assertTrue($fair->verify(Commitment::fromHex($published['commitment']), Seed::fromHex($published['seed']), 'player', 7, $pool, $published['result']));
        self::assertFalse($fair->verify($roll->commitment, $seed, 'player', 8, $pool, $published['result']));
        self::assertFalse($fair->verify($roll->commitment, $seed, 'another', 7, $pool, $published['result']));
        self::assertFalse($fair->verify(Seed::fromHex(str_repeat('0', 64))->commitment(), $seed, 'player', 7, $pool, $published['result']));
        self::assertFalse($fair->verify($roll->commitment, Seed::fromHex(str_repeat('0', 64)), 'player', 7, $pool, $published['result']));
        self::assertTrue($roll->result->equals($fair->roll($pool, $seed, 'player', 7)->result));
        self::assertSame($fair->roll($pool, $seed)->result->toArray(), $fair->roll($pool, $seed, '', 0)->result->toArray());
    }

    public function testAReplayableRollReplaysDieForDie(): void
    {
        $pool = new DiceParser()->parse('10d6!kh3, 4dF');
        $replayer = new Replayer();
        $secure = ScriptedEngine::randomizer(...range(1, 12));
        $roll = $replayer->roll($pool, $secure);

        self::assertSame('01000000020000000300000004000000', $roll->id);
        self::assertSame('05000000060000000700000008000000090000000a0000000b0000000c000000', $roll->seed->hex());
        self::assertSame('10d6!kh3, 4dF', $roll->expression());
        self::assertTrue($roll->result->equals($replayer->replay($roll->id, $pool, $roll->seed)->result));
        self::assertTrue($roll->result->equals(new FairRoller()->roll($pool, $roll->seed)->result));
        self::assertSame($roll->id, $replayer->replay($roll->id, $pool, $roll->seed)->id);
    }

    public function testARollIsVerifiedAndReplayedWithItsRounding(): void
    {
        $pool = new DiceParser()->parse('1d6+7/2');
        $seed = Seed::fromHex(self::SEED);
        $fair = new FairRoller();
        $up = $fair->roll($pool, $seed, 'player', 1, RoundingMode::PositiveInfinity);
        $down = $fair->roll($pool, $seed, 'player', 1);

        self::assertSame([1, 'PositiveInfinity', 'NegativeInfinity'], [$up->result->results[0]->result->value() - $down->result->results[0]->result->value(), $up->toArray()['rounding'], $down->toArray()['rounding']]);
        self::assertTrue($fair->verify($up->commitment, $seed, 'player', 1, $pool, $up->result->toArray(), RoundingMode::PositiveInfinity));
        self::assertFalse($fair->verify($up->commitment, $seed, 'player', 1, $pool, $up->result->toArray()));

        $replayer = new Replayer();
        $roll = $replayer->roll($pool, ScriptedEngine::randomizer(...range(1, 12)), RoundingMode::PositiveInfinity);
        self::assertSame(RoundingMode::PositiveInfinity, $roll->result->rounding);
        self::assertTrue($roll->result->equals($replayer->replay($roll->id, $pool, $roll->seed, RoundingMode::PositiveInfinity)->result));
        self::assertFalse($roll->result->equals($replayer->replay($roll->id, $pool, $roll->seed)->result));
    }

    public function testAReplayIdentifierIsThirtyTwoHexadecimalDigits(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A replay identifier is 32 lower-case hexadecimal digits.'));
        new Replayer()->replay(str_repeat('a', 31), new DiceParser()->parse('1d6'), Seed::fromHex(self::SEED));
    }
}
