<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Quests;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsTables;

/**
 * The quests' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export lists what the member's activity brought to the quests, their
 * acceptances and abandonments, the quests they accomplished and their duels; the erasure deletes all of it. What a
 * team or the community accomplished stays, without the member's contributions.
 */
final readonly class QuestsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_quests';

    public function __construct(
        private Quests $quests,
        private string $memberKind,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{contributions: list<array{quest: string, step: string, member: string, activity: string, value: int, occurred_at: string}>, enrolments: list<array{quest: string, member: string, accepted_at: string, team: ?string, abandoned_at: ?string}>, completions: list<array{quest: string, occurrence: string, subject: string, completed_at: string, activity: string}>, duels: list<array{key: string, quest: string, challenger: string, opponent: string, offered_at: string, status: string, accepted_at: ?string, ends_at: ?string, winner: ?string, decided_at: ?string}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $data = $this->quests->export($this->member($subject));

        if ([] === $data['contributions'] && [] === $data['enrolments'] && [] === $data['completions'] && [] === $data['duels']) {
            return [];
        }

        return [
            'contributions' => array_map(static fn (Contribution $contribution): array => $contribution->toArray(), $data['contributions']),
            'enrolments' => array_map(static fn (Enrolment $enrolment): array => $enrolment->toArray(), $data['enrolments']),
            'completions' => array_map(static fn (Completion $completion): array => $completion->toArray(), $data['completions']),
            'duels' => array_map(static fn (Duel $duel): array => $duel->toArray(), $data['duels']),
        ];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->quests->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            QuestsTables::CONTRIBUTIONS => ErasureEffect::Deleted,
            QuestsTables::ENROLMENTS => ErasureEffect::Deleted,
            QuestsTables::COMPLETIONS => ErasureEffect::Deleted,
            QuestsTables::DUELS => ErasureEffect::Deleted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
