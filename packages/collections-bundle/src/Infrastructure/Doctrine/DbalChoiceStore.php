<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Dice\ChoiceStore;
use Tilttek\Collections\Dice\DieChoice;

/**
 * The dice the members chose in `tilttek_collections_choice`, through a DBAL connection: one row per member and number
 * of sides, replaced in a transaction.
 */
final readonly class DbalChoiceStore implements ChoiceStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(DieChoice $choice): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($choice): void {
            $connection->delete(CollectionsTables::CHOICES, ['member' => $choice->member->toString(), 'sides' => $choice->sides], [ParameterType::STRING, ParameterType::INTEGER]);
            $connection->insert(CollectionsTables::CHOICES, ['member' => $choice->member->toString(), 'sides' => $choice->sides, 'copy_id' => $choice->copy], [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING]);
        });
    }

    #[Override]
    public function find(ResourceReference $member, int $sides): ?DieChoice
    {
        return $this->read('WHERE member = ? AND sides = ?', [$member->toString(), $sides])[0] ?? null;
    }

    #[Override]
    public function remove(ResourceReference $member, int $sides): bool
    {
        return 0 < $this->connection->delete(CollectionsTables::CHOICES, ['member' => $member->toString(), 'sides' => $sides], [ParameterType::STRING, ParameterType::INTEGER]);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE member = ?', [$member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->delete(CollectionsTables::CHOICES, ['member' => $member->toString()]));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<DieChoice>
     */
    private function read(string $where, array $parameters): array
    {
        return array_map(
            static fn (array $row): DieChoice => new DieChoice(Rows::reference($row['member']), Rows::integer($row['sides']), Rows::text($row['copy_id'])),
            $this->connection->fetchAllAssociative(\sprintf('SELECT member, sides, copy_id FROM %s %s ORDER BY sides', CollectionsTables::CHOICES, $where), $parameters),
        );
    }
}
