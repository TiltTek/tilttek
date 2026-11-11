<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Progression;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakStatus;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionTables;

/**
 * The progression's part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export lists the member's experience gains, the milestones they
 * reached and where they stand with each streak; the erasure deletes their gains and those their gestures brought to
 * a character, their milestones, and their streaks — gestures, frozen days and states.
 */
final readonly class ProgressionPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_progression';

    public function __construct(
        private Progression $progression,
        private string $memberKind,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{gains: list<array{track: string, rule: string, subject: string, member: string, activity: string, amount: int, occurred_at: string, season: string}>, milestones: list<array{track: string, milestone: string, subject: string, member: string, season: string, reached_at: string, activity: string}>, streaks: list<array{streak: string, current: int, best: int}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);
        $gains = $this->progression->gains($member);
        $milestones = $this->progression->milestones($member);
        $streaks = array_values(array_filter($this->progression->streaks($member), static fn (StreakStatus $status): bool => 0 < $status->best));

        if ([] === $gains && [] === $milestones && [] === $streaks) {
            return [];
        }

        return [
            'gains' => array_map(static fn (Gain $gain): array => $gain->toArray(), $gains),
            'milestones' => array_map(static fn (ReachedMilestone $reached): array => $reached->toArray(), $milestones),
            'streaks' => array_map(static fn (StreakStatus $status): array => ['streak' => $status->streak, 'current' => $status->current, 'best' => $status->best], $streaks),
        ];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->progression->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            ProgressionTables::GAINS => ErasureEffect::Deleted,
            ProgressionTables::MILESTONES => ErasureEffect::Deleted,
            ProgressionTables::GESTURES => ErasureEffect::Deleted,
            ProgressionTables::FREEZES => ErasureEffect::Deleted,
            ProgressionTables::STATES => ErasureEffect::Deleted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
