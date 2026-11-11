<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Replay\Replayer;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(RecordedRoll::class)]
final class RecordedRollTest extends TestCase
{
    public function testARecordedRollKeepsWhatReplaysIt(): void
    {
        $roll = new Replayer()->roll(new DiceParser()->parse('4D6KH3, 1d20'), ScriptedEngine::randomizer(...range(1, 12)));
        $at = new DateTimeImmutable('2026-11-07 03:00:00 UTC');
        $recorded = RecordedRoll::record($roll, ResourceReference::of('member', 'a:b'), $at);

        self::assertSame(['01000000020000000300000004000000', '4d6kh3, 1d20', $roll->seed->hex(), $roll->result->toArray(), 'member:a:b', $at], [$recorded->id(), $recorded->expression(), $recorded->seed()->hex(), $recorded->result(), $recorded->owner()?->toString(), $recorded->rolledAt()]);
        self::assertNull(RecordedRoll::record($roll, null, $at)->owner());
        self::assertSame(RoundingMode::NegativeInfinity, $recorded->rounding());
    }

    public function testARecordedRollKeepsItsRounding(): void
    {
        $at = new DateTimeImmutable('2026-11-07 03:00:00 UTC');

        foreach (RoundingMode::cases() as $mode) {
            $roll = new Replayer()->roll(new DiceParser()->parse('1d6/4'), ScriptedEngine::randomizer(...range(1, 12)), $mode);

            self::assertSame($mode, RecordedRoll::record($roll, null, $at)->rounding());
        }

        $recorded = Closure::bind(static fn (): RecordedRoll => new RecordedRoll(str_repeat('0', 32), '1d6', str_repeat('0', 64), [], null, $at, 'Sideways'), null, RecordedRoll::class)();

        self::assertSame(RoundingMode::NegativeInfinity, $recorded->rounding());
    }
}
