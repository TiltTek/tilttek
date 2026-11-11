<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\QuestsBundle\Infrastructure\Privacy\QuestsPrivacy;
use Tilttek\QuestsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(QuestsPrivacy::class)]
final class QuestsPrivacyTest extends TestCase
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
        $kit->at('2026-11-06 09:00');
        $kit->quests->abandon(ResourceReference::fromString('member:7'), 'two');
        $kit->duelService->challenge('d1', 'race', ResourceReference::fromString('member:7'), ResourceReference::fromString('member:8'));
        $kit->record('k1', 'member:7', 'game.played', '2026-11-06 10:00');
        $kit->record('k2', 'member:8', 'game.played', '2026-11-06 10:00');

        $privacy = new QuestsPrivacy($kit->quests, 'member');

        self::assertSame('tilttek_quests', $privacy->module()->value);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));
        self::assertSame([
            'contributions' => [
                ['quest' => 'one', 'step' => 'play', 'member' => 'member:7', 'activity' => 'k1', 'value' => 1, 'occurred_at' => '2026-11-06T10:00:00+00:00'],
                ['quest' => 'two', 'step' => 'play', 'member' => 'member:7', 'activity' => 'k1', 'value' => 1, 'occurred_at' => '2026-11-06T10:00:00+00:00'],
            ],
            'enrolments' => [['quest' => 'two', 'member' => 'member:7', 'accepted_at' => '2026-11-06T09:00:00+00:00', 'team' => null, 'abandoned_at' => '2026-11-06T09:00:00+00:00']],
            'completions' => [['quest' => 'one', 'occurrence' => '', 'subject' => 'member:7', 'completed_at' => '2026-11-06T10:00:00+00:00', 'activity' => 'k1']],
            'duels' => [[
                'key' => 'd1', 'quest' => 'race', 'challenger' => 'member:7', 'opponent' => 'member:8', 'offered_at' => '2026-11-06T09:00:00+00:00',
                'status' => 'offered', 'accepted_at' => null, 'ends_at' => null, 'winner' => null, 'decided_at' => null,
            ]],
        ], $privacy->export(DataSubject::fromString('7')));

        $report = $privacy->erase(DataSubject::fromString('7'));

        self::assertSame([
            'tilttek_quests_contribution' => ErasureEffect::Deleted,
            'tilttek_quests_enrolment' => ErasureEffect::Deleted,
            'tilttek_quests_completion' => ErasureEffect::Deleted,
            'tilttek_quests_duel' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('7')));
        self::assertCount(1, $kit->quests->completions(ResourceReference::fromString('member:8')));
        self::assertSame([], new QuestsPrivacy($kit->quests, 'user')->export(DataSubject::fromString('8')));
    }

    public function testAnExportOfEachPartAlone(): void
    {
        $kit = $this->kit();
        $privacy = new QuestsPrivacy($kit->quests, 'member');
        $kit->at('2026-11-06 09:00');
        $kit->quests->abandon(ResourceReference::fromString('member:1'), 'two');
        $kit->duelService->challenge('d1', 'race', ResourceReference::fromString('member:2'), ResourceReference::fromString('member:3'));
        $kit->completions->add(new Completion('one', '', ResourceReference::fromString('member:4'), new DateTimeImmutable('2026-11-06 UTC'), 'k1'));
        $kit->record('k2', 'member:5', 'news.read', '2026-11-06 10:00');
        $kit->contributions->add(new Contribution('two', 'play', ResourceReference::fromString('member:5'), 'k2', 1, new DateTimeImmutable('2026-11-06 10:00 UTC')));

        self::assertCount(1, $privacy->export(DataSubject::fromString('1'))['enrolments'] ?? []);
        self::assertCount(1, $privacy->export(DataSubject::fromString('3'))['duels'] ?? []);
        self::assertCount(1, $privacy->export(DataSubject::fromString('4'))['completions'] ?? []);
        self::assertCount(1, $privacy->export(DataSubject::fromString('5'))['contributions'] ?? []);
    }

    private function kit(): Kit
    {
        return Kit::of([
            'one' => ['steps' => [['code' => 'play', 'verbs' => ['game.played']]]],
            'two' => ['steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
            'race' => ['kind' => 'duel', 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
        ]);
    }
}
