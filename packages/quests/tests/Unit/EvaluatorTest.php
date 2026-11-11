<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\Tests\Unit\Fixture\Kit;
use Tilttek\Quests\Window;

#[CoversClass(Evaluator::class)]
#[CoversClass(Enrolment::class)]
final class EvaluatorTest extends TestCase
{
    public function testADuelHasNoSubjectOfItsOwn(): void
    {
        $kit = Kit::of(['race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]]]]);

        self::assertNull($kit->evaluator->subjectOf($kit->catalog->get('race'), Kit::ref('member:1')));
    }

    public function testAnEnrolmentWithoutTeamCountsForNoTeam(): void
    {
        $kit = Kit::of(['raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played']]]]]);
        $raid = $kit->catalog->get('raid');
        $kit->enrolments->save(new Enrolment('raid', Kit::ref('member:1'), new DateTimeImmutable('2026-11-01 09:00 UTC')));
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');

        self::assertNull($kit->evaluator->subjectOf($raid, Kit::ref('member:1')));
        self::assertSame(0, $kit->evaluator->progress($raid, Kit::ref('team:red'), new Window())->steps[0]->value);
        self::assertSame([], $kit->recorder->events());
    }
}
