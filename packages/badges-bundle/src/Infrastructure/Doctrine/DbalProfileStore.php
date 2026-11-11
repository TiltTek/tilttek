<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Profile;
use Tilttek\Badges\Store\ProfileStore;
use UnexpectedValueException;

/**
 * The profiles in `tilttek_badges_profile`, through a DBAL connection: one row per member who shows a badge, the badges
 * shown and the showcase as JSON lists.
 */
final readonly class DbalProfileStore implements ProfileStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function get(ResourceReference $member): Profile
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT shown, featured, title FROM %s WHERE member = ?', BadgesTables::PROFILES), [$member->toString()]);

        if (false === $row) {
            return new Profile($member);
        }

        return new Profile($member, $this->codes($row['shown']), $this->codes($row['featured']), null === $row['title'] ? null : Rows::text($row['title']));
    }

    #[Override]
    public function save(Profile $profile): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($profile): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', BadgesTables::PROFILES), [$profile->member->toString()]);

            if ($profile->isEmpty()) {
                return;
            }

            $connection->executeStatement(
                \sprintf('INSERT INTO %s (member, shown, featured, title) VALUES (?, ?, ?, ?)', BadgesTables::PROFILES),
                [$profile->member->toString(), $profile->shown, $profile->featured, $profile->title],
                [ParameterType::STRING, Types::JSON, Types::JSON, ParameterType::STRING],
            );
        });
    }

    #[Override]
    public function erase(ResourceReference $member): bool
    {
        return 0 < $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', BadgesTables::PROFILES), [$member->toString()]);
    }

    /**
     * @return list<string>
     */
    private function codes(mixed $json): array
    {
        $codes = json_decode(Rows::text($json), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($codes)) {
            throw new UnexpectedValueException('A profile column holds a list of badge codes.');
        }

        $list = [];

        foreach ($codes as $code) {
            if (!\is_string($code)) {
                throw new UnexpectedValueException('A profile column holds a list of badge codes.');
            }

            $list[] = $code;
        }

        return $list;
    }
}
