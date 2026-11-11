<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;
use Tilttek\Badges\Badges;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesTables;

/**
 * The badges' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export lists the member's awards, what they show — the badges, the
 * showcase, the title — and the applications they allow to read their badges; the erasure deletes all of it, with the
 * contributions of their activity to the badges' criteria.
 */
final readonly class BadgesPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_badges';

    public function __construct(
        private Badges $badges,
        private string $memberKind,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{awards: list<array{badge: string, member: string, number: int, awarded_at: string, cause: ?string, manual: bool}>, shown: list<string>, featured: list<string>, title: ?string, clients: list<string>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $this->member($subject);
        $awards = $this->badges->awards($member);
        $profile = $this->badges->profile($member);
        $clients = $this->badges->clients($member);

        if ([] === $awards && $profile->isEmpty() && [] === $clients) {
            return [];
        }

        return [
            'awards' => array_map(static fn (Award $award): array => $award->toArray(), $awards),
            'shown' => $profile->shown,
            'featured' => $profile->featured,
            'title' => $profile->title,
            'clients' => $clients,
        ];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->badges->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            BadgesTables::AWARDS => ErasureEffect::Deleted,
            BadgesTables::CONTRIBUTIONS => ErasureEffect::Deleted,
            BadgesTables::PROFILES => ErasureEffect::Deleted,
            BadgesTables::GRANTS => ErasureEffect::Deleted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
