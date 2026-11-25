<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetyTables;

/**
 * The safety tools' part of an access or erasure request. The data subject is a participant, referenced under the
 * kind the configuration names (`member` by default). The export holds their answers at each table, the named signals
 * they raised and their stars and wishes; the erasure deletes the answers and the stars and wishes, and takes their
 * name off the signals, which stay at their table as anonymous ones until the purge — redacted rather than anonymised,
 * since the table and the moment of a signal may still point to the participant at a small table. `doc/privacy.md`
 * gives the reason of each choice.
 */
final readonly class TableSafetyPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_table_safety';

    public function __construct(
        private TableSafety $safety,
        private string $participantKind,
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
        $export = $this->safety->export($this->participant($subject));

        return [] === $export['participations'] && [] === $export['signals'] && [] === $export['feedback'] ? [] : $export;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->safety->erase($this->participant($subject));

        return ErasureReport::of(self::MODULE, [
            TableSafetyTables::PARTICIPATIONS => ErasureEffect::Deleted,
            TableSafetyTables::FEEDBACK => ErasureEffect::Deleted,
            TableSafetyTables::SIGNALS => ErasureEffect::Redacted,
        ]);
    }

    private function participant(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->participantKind, $subject->value);
    }
}
