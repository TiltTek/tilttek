<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Privacy;

use Override;
use Phil\PrivacyBundle\Application\Port\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Port\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\MemberData;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\CollectionsTables;

/**
 * The collections' part of an access or erasure request. The data subject is a member, referenced under the kind the
 * configuration names (`member` by default). The export lists the member's copies, the sets they completed, the packs
 * they opened and the dice they chose; the erasure deletes all of it. The copies the member traded away belong to
 * other members and stay theirs.
 */
final readonly class CollectionsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'tilttek_collections';

    public function __construct(
        private MemberData $data,
        private string $memberKind,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    /**
     * @return array{}|array{copies: list<array{id: string, item: string, owner: string, source: string, key: string, acquired_at: string, tradable: bool, moved: bool}>, completions: list<array{set: string, member: string, completed_at: string}>, openings: list<array{key: string, member: string, pack: string, seed: string, commitment: string, client_seed: string, items: list<string>, copies: list<string>, opened_at: string}>, choices: list<array{member: string, sides: int, copy: string}>}
     */
    #[Override]
    public function export(DataSubject $subject): array
    {
        $data = $this->data->export($this->member($subject));

        return [] === array_merge(...array_values($data)) ? [] : $data;
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->data->erase($this->member($subject));

        return ErasureReport::of(self::MODULE, [
            CollectionsTables::COPIES => ErasureEffect::Deleted,
            CollectionsTables::COMPLETIONS => ErasureEffect::Deleted,
            CollectionsTables::OPENINGS => ErasureEffect::Deleted,
            CollectionsTables::CHOICES => ErasureEffect::Deleted,
        ]);
    }

    private function member(DataSubject $subject): ResourceReference
    {
        return ResourceReference::of($this->memberKind, $subject->value);
    }
}
