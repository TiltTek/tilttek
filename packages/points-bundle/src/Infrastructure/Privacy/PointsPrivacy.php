<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Points\Account;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;

/**
 * The ledger's part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default): `member:` followed by the subject's identifier. The export lists every
 * movement of the member's account, in every currency; the erasure deletes the movements between the member and the
 * ledger's own accounts, and writes `erased:member` in place of the member in the transfers with other members, which
 * keep their points.
 */
final readonly class PointsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_points';

    public function __construct(
        private Ledger $ledger,
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
     * @return array{}|array{points: non-empty-list<array{position: int, key: string, kind: string, currency: string, debit: string, credit: string, amount: int, occurred_at: string, written_at: string, cause: ?string, reverses: ?string}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $account = Account::member($this->member($subject));
        $movements = [];
        $after = 0;

        do {
            $batch = $this->ledger->statement($account, null, $after, $this->batchSize);

            foreach ($batch as $entry) {
                $movements[] = $entry->toArray();
                $after = $entry->position;
            }
        } while (\count($batch) === $this->batchSize);

        return [] === $movements ? [] : ['points' => $movements];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->ledger->eraseMember($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            PointsTables::MOVEMENTS => ErasureEffect::Deleted,
            PointsTables::MOVEMENTS.'.debit' => ErasureEffect::Redacted,
            PointsTables::MOVEMENTS.'.credit' => ErasureEffect::Redacted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
