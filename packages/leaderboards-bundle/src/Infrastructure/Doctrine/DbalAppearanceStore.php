<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\Leaderboards\Visibility;

/**
 * The members' choices of appearance in `tilttek_leaderboards_appearance`, through a DBAL connection: one row per
 * member who chose to appear; a member without one is hidden.
 */
final readonly class DbalAppearanceStore implements AppearanceStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function choose(ResourceReference $member, Appearance $appearance): void
    {
        $this->connection->transactional(function (Connection $connection) use ($member, $appearance): void {
            $this->eraseMember($member);

            if (Visibility::Hidden !== $appearance->visibility) {
                $connection->insert(LeaderboardsTables::APPEARANCES, ['member' => $member->toString(), 'visibility' => $appearance->visibility->value, 'pseudonym' => $appearance->pseudonym]);
            }
        });
    }

    #[Override]
    public function of(array $members): array
    {
        $chosen = [];

        foreach ($this->connection->fetchAllAssociative(
            \sprintf('SELECT member, visibility, pseudonym FROM %s WHERE member IN (?) ORDER BY member', LeaderboardsTables::APPEARANCES),
            [array_map(static fn (ResourceReference $member): string => $member->toString(), $members)],
            [ArrayParameterType::STRING],
        ) as $row) {
            $chosen[Rows::text($row['member'])] = new Appearance(Visibility::from(Rows::text($row['visibility'])), null === $row['pseudonym'] ? null : Rows::text($row['pseudonym']));
        }

        return $chosen;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', LeaderboardsTables::APPEARANCES), [$member->toString()]));
    }
}
