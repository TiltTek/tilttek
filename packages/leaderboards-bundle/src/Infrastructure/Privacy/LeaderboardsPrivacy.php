<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;

/**
 * The leaderboards' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export holds the member's choice of appearance, their scores, their
 * lines in the snapshots, their tiers and memberships in the leagues, and the leads they took; the erasure deletes their
 * contributions, scores, lines, choice, tiers, memberships and leads — the others keep their ranks and their divisions.
 */
final readonly class LeaderboardsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_leaderboards';

    public function __construct(
        private Leaderboards $leaderboards,
        private string $memberKind,
        private LeadStore $leads = new InMemoryLeadStore(),
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{appearance: array{visibility: string, pseudonym: ?string}, scores: list<array{board: string, period: string, value: int, tie_breaks: list<int>, reached_at: string}>, placings: list<array{board: string, period: string, rank: int, value: int, reached_at: string}>, leagues: array{tiers: array<string, string>, memberships: list<array{board: string, week: string, tier: string, division: int, cohort: int, joined_at: string, movement: ?string}>}, leads: list<array{board: string, period: string, taken_at: string}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);
        $export = $this->leaderboards->export($member);
        $leads = array_map(static fn (Lead $lead): array => $lead->toArray(), $this->leads->leads($member));

        if ('hidden' === $export['appearance']['visibility'] && [] === $export['scores'] && [] === $export['placings'] && [] === $export['leagues']['tiers'] && [] === $export['leagues']['memberships'] && [] === $leads) {
            return [];
        }

        return [...$export, 'leads' => $leads];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $this->member($subject);
        $this->leaderboards->erase($member);
        $this->leads->eraseMember($member);

        return ErasureReport::of(self::MODULE, [
            LeaderboardsTables::CONTRIBUTIONS => ErasureEffect::Deleted,
            LeaderboardsTables::SCORES => ErasureEffect::Deleted,
            LeaderboardsTables::PLACINGS => ErasureEffect::Deleted,
            LeaderboardsTables::APPEARANCES => ErasureEffect::Deleted,
            LeaderboardsTables::LEAGUE_TIERS => ErasureEffect::Deleted,
            LeaderboardsTables::LEAGUE_MEMBERSHIPS => ErasureEffect::Deleted,
            LeaderboardsTables::LEADS => ErasureEffect::Deleted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
