<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Tilttek\Exchange\Exception\UnknownReward;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Exchange\Reward\WritableCatalog;
use UnexpectedValueException;

/**
 * The catalogue in `tilttek_exchange_reward`, through a DBAL connection, for an application whose rewards are edited
 * rather than configured. The conditions are written as a JSON list.
 */
final readonly class DbalCatalog implements WritableCatalog
{
    private const string COLUMNS = 'code, price, currency, stock, available_from, available_until, per_member, requires';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function get(string $code): Reward
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE code = ?', self::COLUMNS, ExchangeTables::REWARDS), [$code]);

        return false === $row ? throw UnknownReward::named($code) : $this->hydrate($row);
    }

    #[Override]
    public function all(): array
    {
        $rewards = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s ORDER BY code', self::COLUMNS, ExchangeTables::REWARDS)) as $row) {
            $rewards[] = $this->hydrate($row);
        }

        return $rewards;
    }

    #[Override]
    public function save(Reward $reward): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($reward): void {
            $connection->delete(ExchangeTables::REWARDS, ['code' => $reward->code]);
            $connection->insert(ExchangeTables::REWARDS, [
                'code' => $reward->code,
                'price' => $reward->price,
                'currency' => $reward->currency,
                'stock' => $reward->stock,
                'available_from' => $reward->availableFrom instanceof DateTimeImmutable ? $reward->availableFrom : null,
                'available_until' => $reward->availableUntil instanceof DateTimeImmutable ? $reward->availableUntil : null,
                'per_member' => $reward->perMember,
                'requires' => json_encode($reward->requires, \JSON_THROW_ON_ERROR),
            ], [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE, ParameterType::INTEGER, ParameterType::STRING]);
        });
    }

    #[Override]
    public function remove(string $code): void
    {
        $this->connection->delete(ExchangeTables::REWARDS, ['code' => $code]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Reward
    {
        return new Reward(
            Rows::text($row['code']),
            Rows::integer($row['price']),
            Rows::nullableText($row['currency']),
            Rows::nullableInteger($row['stock']),
            Rows::nullableDate($row['available_from']),
            Rows::nullableDate($row['available_until']),
            Rows::nullableInteger($row['per_member']),
            $this->requirements(Rows::text($row['requires'])),
        );
    }

    /**
     * @return list<string>
     */
    private function requirements(string $json): array
    {
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $requires = [];

        foreach (\is_array($decoded) ? $decoded : [$decoded] as $requirement) {
            if (!\is_string($requirement)) {
                throw new UnexpectedValueException(\sprintf('The conditions of a reward are a JSON list of strings; "%s" is not.', $json));
            }

            $requires[] = $requirement;
        }

        return $requires;
    }
}
