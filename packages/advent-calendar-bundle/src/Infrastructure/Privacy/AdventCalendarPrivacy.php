<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\AdventCalendarTables;

/**
 * The calendars' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export holds the boxes they opened, in every calendar, with the
 * moment of each; the erasure deletes those openings. What the surprises granted — points, badges, codes — lives in the
 * application, which erases it with the rest of the member's data.
 */
final readonly class AdventCalendarPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_advent_calendar';

    public function __construct(
        private AdventCalendar $advent,
        private MemberOpenings $openings,
        private string $memberKind,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $openings = $this->openings->ofMember($this->member($subject));

        return [] === $openings ? [] : ['openings' => array_map(static fn (Opening $opening): array => $opening->toArray(), $openings)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->advent->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [AdventCalendarTables::OPENINGS => ErasureEffect::Deleted]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
