<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Dialect\Dialects;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\CannotRoll as Refused;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Exception\UntranslatableExpression;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(DiceRoller::class)]
#[CoversClass(InvalidExpression::class)]
#[CoversClass(InvalidDefinition::class)]
final class DiceRollerTest extends TestCase
{
    public function testTheLibraryInOneObject(): void
    {
        $dice = new DiceRoller(new Limits(dice: 10), new CustomDice(new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0))), new Notations(['fi' => new Notation('n')]), new Dialects());
        $pool = $dice->parse('2W6+1d{coin}', 'de');

        self::assertSame(['2d6+1d{coin}', '2W6+1W{coin}', '2n6+1n{coin}'], [$dice->write($pool), $dice->write($pool, 'de_DE'), $dice->write($pool, 'fi')]);
        self::assertSame('2d6+1', $dice->write($dice->parse('2n6+1', 'fi')));
        self::assertSame([8], $dice->roll($pool, ScriptedEngine::randomizer(2, 3, 0))->totals());
        self::assertFalse($dice->translate($pool, 'roll20')->isComplete());

        $seed = Seed::fromHex(str_repeat('ab', 32));
        $verifiable = $dice->rollVerifiable($dice->parse('1d{coin}+4d6'), $seed, 'player', 2);
        self::assertTrue($dice->fairRoller()->verify($verifiable->commitment, $seed, 'player', 2, $dice->parse('1d{coin}+4d6'), $verifiable->result->toArray()));
        self::assertSame([$seed, 'player', 2], [$verifiable->seed, $verifiable->clientSeed, $verifiable->nonce]);
        self::assertTrue($verifiable->result->equals($dice->rollVerifiable($dice->parse('1d{coin}+4d6'), $seed, 'player', 2)->result));
        self::assertTrue($dice->rollVerifiable($dice->parse('4d6'), $seed)->result->equals($dice->rollVerifiable($dice->parse('4d6'), $seed, '', 0)->result));

        $replayable = $dice->rollReplayable($dice->parse('3d{coin}'), ScriptedEngine::randomizer(...range(1, 12)));
        self::assertTrue($replayable->result->equals($dice->replay($replayable->id, $dice->parse('3d{coin}'), $replayable->seed)->result));
    }

    public function testTheFacadeRoundsAsTheCallerSays(): void
    {
        $dice = new DiceRoller();
        $pool = $dice->parse('7/2');
        $seed = Seed::fromHex(str_repeat('ab', 32));

        self::assertSame([[3], [4]], [$dice->roll($pool, ScriptedEngine::randomizer())->totals(), $dice->roll($pool, ScriptedEngine::randomizer(), RoundingMode::PositiveInfinity)->totals()]);
        self::assertSame([3, 4], [$dice->rollVerifiable($pool, $seed)->result->totals()[0], $dice->rollVerifiable($pool, $seed, 'x', 1, RoundingMode::PositiveInfinity)->result->totals()[0]]);

        $replayable = $dice->rollReplayable($pool, ScriptedEngine::randomizer(...range(1, 12)), RoundingMode::PositiveInfinity);
        self::assertSame([[4], [4], [3]], [$replayable->result->totals(), $dice->replay($replayable->id, $pool, $replayable->seed, RoundingMode::PositiveInfinity)->result->totals(), $dice->replay($replayable->id, $pool, $replayable->seed)->result->totals()]);
    }

    public function testTheFacadeKeepsItsLimitsAndCustomDice(): void
    {
        $dice = new DiceRoller(new Limits(dice: 1));

        try {
            $dice->parse('2d6');
            self::fail('The limit was ignored.');
        } catch (LimitExceeded) {
        }

        try {
            $dice->rollReplayable(new DiceRoller()->parse('2d6'), ScriptedEngine::randomizer(...range(1, 12)));
            self::fail('The limit was ignored.');
        } catch (LimitExceeded) {
        }

        $this->expectException(Refused::class);
        new DiceRoller()->roll($dice->parse('1d{coin}'), ScriptedEngine::randomizer());
    }

    public function testEveryExceptionIsADiceException(): void
    {
        self::assertContainsOnlyInstancesOf(DiceException::class, [InvalidExpression::at('x', 0, 'Reason'), LimitExceeded::of('dice', 1, 2), Refused::overflow(), InvalidDefinition::because('Reason'), UntranslatableExpression::in('avrae', ['4dF'])]);
    }
}
