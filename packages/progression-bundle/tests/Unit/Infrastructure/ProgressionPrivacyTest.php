<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tracks;
use Tilttek\ProgressionBundle\Infrastructure\Privacy\ProgressionPrivacy;
use Tilttek\ProgressionBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(ProgressionPrivacy::class)]
final class ProgressionPrivacyTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!interface_exists(PersonalDataEraser::class)) {
            self::markTestSkipped('phil/privacy-bundle is not installed: the export and erasure of a member are an optional integration.');
        }
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:7', 'game.played', '2026-11-06 10:00', quantity: 3);
        $kit->record('k2', 'member:8', 'game.played', '2026-11-06 10:00');

        $privacy = new ProgressionPrivacy($kit->progression, 'member');

        self::assertSame('tilttek_progression', $privacy->module()->value);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));
        self::assertSame([
            'gains' => [['track' => 'player', 'rule' => 'games', 'subject' => 'member:7', 'member' => 'member:7', 'activity' => 'k1', 'amount' => 150, 'occurred_at' => '2026-11-06T10:00:00+00:00', 'season' => '']],
            'milestones' => [['track' => 'player', 'milestone' => 'first', 'subject' => 'member:7', 'member' => 'member:7', 'season' => '', 'reached_at' => '2026-11-06T10:00:00+00:00', 'activity' => 'k1']],
            'streaks' => [['streak' => 'daily', 'current' => 1, 'best' => 1]],
        ], $privacy->export(DataSubject::fromString('7')));

        $report = $privacy->erase(DataSubject::fromString('7'));

        self::assertSame([
            'tilttek_progression_gain' => ErasureEffect::Deleted,
            'tilttek_progression_milestone' => ErasureEffect::Deleted,
            'tilttek_progression_streak_gesture' => ErasureEffect::Deleted,
            'tilttek_progression_streak_freeze' => ErasureEffect::Deleted,
            'tilttek_progression_streak_state' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('7')));
        self::assertSame(50, $kit->progression->standing(ResourceReference::fromString('member:8'), 'player')->lifetime);
        self::assertSame([], new ProgressionPrivacy($kit->progression, 'user')->export(DataSubject::fromString('8')));
    }

    public function testAnExportOfStreaksOnly(): void
    {
        $kit = new Kit(Tracks::fromArray([]), StreakRules::fromArray(['daily' => ['verbs' => ['forum.posted']], 'visits' => ['verbs' => ['site.visited'], 'unit' => 'week']]));
        $kit->record('k1', 'member:7', 'site.visited', '2026-11-07 10:00');

        self::assertSame(['gains' => [], 'milestones' => [], 'streaks' => [['streak' => 'visits', 'current' => 1, 'best' => 1]]], new ProgressionPrivacy($kit->progression, 'member')->export(DataSubject::fromString('7')));
    }

    public function testAnExportOfMilestonesOnly(): void
    {
        $kit = $this->kit();
        $kit->milestones->reach(new ReachedMilestone('player', 'first', ResourceReference::fromString('member:7'), ResourceReference::fromString('member:7'), '', new DateTimeImmutable('2026-11-07 UTC'), 'k1'));

        self::assertCount(1, new ProgressionPrivacy($kit->progression, 'member')->export(DataSubject::fromString('7'))['milestones'] ?? []);
    }

    private function kit(): Kit
    {
        return new Kit(Tracks::fromArray([
            'player' => [
                'curve' => ['type' => 'linear', 'step' => 100],
                'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 50]],
                'milestones' => ['first' => ['experience' => 100]],
            ],
        ]), StreakRules::fromArray(['daily' => ['verbs' => ['game.played']]]));
    }
}
