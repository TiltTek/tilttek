<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\Signal\SignalKind;

#[CoversClass(SafetyTool::class)]
#[CoversClass(SignalKind::class)]
final class SafetyToolTest extends TestCase
{
    public function testEachToolIsCredited(): void
    {
        self::assertSame('Ron Edwards', SafetyTool::LinesAndVeils->credit());
        self::assertSame('John Stavropoulos', SafetyTool::XCard->credit());
        self::assertSame('Beau Jágr Sheldon', SafetyTool::ScriptChange->credit());
        self::assertSame('Sean K. Reynolds and Shanna Germain', SafetyTool::ConsentChecklist->credit());
        self::assertSame('Eirik Fatland', SafetyTool::OpenDoor->credit());
        self::assertSame('Lu Quade', SafetyTool::StarsAndWishes->credit());

        foreach ([SafetyTool::SessionZero, SafetyTool::CheckIn] as $tool) {
            self::assertSame('the tabletop role-playing community', $tool->credit());
        }
    }

    public function testTheToolsAreInTheOrderOfAGame(): void
    {
        self::assertSame(SafetyTool::cases(), SafetyTool::all());
        self::assertSame(SafetyTool::LinesAndVeils, SafetyTool::all()[0]);
        self::assertSame(SafetyTool::StarsAndWishes, SafetyTool::all()[7]);
    }

    public function testEachToolRaisesItsSignals(): void
    {
        self::assertSame([SignalKind::XCard], SafetyTool::XCard->signals());
        self::assertSame([SignalKind::Pause, SignalKind::Rewind, SignalKind::FastForward], SafetyTool::ScriptChange->signals());
        self::assertSame([SignalKind::OpenDoor], SafetyTool::OpenDoor->signals());
        self::assertSame([SignalKind::CheckIn, SignalKind::AllGood, SignalKind::Unsure, SignalKind::NotOkay], SafetyTool::CheckIn->signals());
        self::assertSame([], SafetyTool::LinesAndVeils->signals());
        self::assertSame([], SafetyTool::StarsAndWishes->signals());
    }

    public function testOnlyTheCheckInIsTheGameMasters(): void
    {
        foreach (SignalKind::cases() as $kind) {
            self::assertSame(SignalKind::CheckIn === $kind, $kind->isGameMasters(), $kind->value);
        }
    }

    public function testTheAnswersOfACheckIn(): void
    {
        $answers = array_values(array_filter(SignalKind::cases(), static fn (SignalKind $kind): bool => $kind->isAnswer()));

        self::assertSame([SignalKind::AllGood, SignalKind::Unsure, SignalKind::NotOkay], $answers);
    }
}
