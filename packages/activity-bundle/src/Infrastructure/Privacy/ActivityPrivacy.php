<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;

/**
 * The journal's part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default): `member:` followed by the subject's identifier. The export lists every
 * entry of the member's gestures; the erasure deletes them and forgets the member as the object of others' gestures,
 * which stay, since they are the other members' activity.
 */
final readonly class ActivityPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_activity';

    public function __construct(
        private Journal $journal,
        private string $memberKind,
        private int $batchSize,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{activity: non-empty-list<array{position: int, kind: string, written_at: string, key: string, member: string, verb: string, occurred_at: string, object: ?string, quantity: int, context: array<string, bool|int|string>}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $filter = Filter::all()->member($this->member($subject));
        $entries = [];
        $after = 0;

        do {
            $batch = $this->journal->read($after, $this->batchSize, $filter);

            foreach ($batch as $entry) {
                $entries[] = ['position' => $entry->position, 'kind' => $entry->kind->value, 'written_at' => $entry->at->format(DateTimeInterface::ATOM)] + $entry->activity->toArray();
                $after = $entry->position;
            }
        } while (\count($batch) === $this->batchSize);

        return [] === $entries ? [] : ['activity' => $entries];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->journal->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            ActivityTables::ENTRIES => ErasureEffect::Deleted,
            ActivityTables::ENTRIES.'.object' => ErasureEffect::Redacted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
