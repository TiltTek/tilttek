<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Generator;
use JsonException;
use Override;
use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Move;

/**
 * The logs of the tables in `tilttek_game_engine_table` and `tilttek_game_engine_move`, through a DBAL connection, on
 * MySQL, PostgreSQL or SQLite.
 *
 * The header of a log is one row of the tables, with its number of moves and its revision; the moves are read window by
 * window, `WINDOW` at a time, as they are asked for. Appending a move changes the number of moves and the revision of
 * the table in one statement that compares both with those the caller read, then inserts the move, in one transaction:
 * of two changes made at once, the statement of the second changes no row, and it is refused with `ConcurrentMove`;
 * the primary key of the moves keeps out a second move of one number whatever happens. A whole log is read back up to
 * the number of moves of its table, read again when its revision changed meanwhile, every field checked by the
 * library.
 */
final readonly class DbalLogStore implements LogStore
{
    /** The moves one query reads at most. */
    public const int WINDOW = 256;

    private const int JSON_DEPTH = 16;

    /** The reads of a whole log that changes while it is read, before giving up. */
    private const int READS = 3;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function create(GameLog $log): void
    {
        try {
            $this->connection->transactional(static function (Connection $connection) use ($log): void {
                $connection->insert(GameEngineTables::TABLES, [
                    'game_id' => $log->gameId,
                    'game' => $log->game,
                    'version' => $log->version,
                    'seed' => $log->seed,
                    'seats' => self::encode($log->seats),
                    'options' => self::encode($log->options),
                    'moves' => $log->sequence(),
                    'revision' => $log->sequence(),
                ]);

                foreach ($log->moves as $move) {
                    self::insert($connection, $log->gameId, $move);
                }
            });
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw InvalidGame::because(\sprintf('A table has the identifier "%s".', $log->gameId), $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function load(string $gameId): ?GameLog
    {
        for ($read = 1; $read <= self::READS; ++$read) {
            $header = $this->header($gameId);

            if (!$header instanceof LogHeader) {
                return null;
            }

            $moves = [...$this->moves($gameId, 1, $header->sequence)];

            if (\count($moves) === $header->sequence && $this->header($gameId)?->revision === $header->revision) {
                return new GameLog($gameId, $header->game, $header->version, $header->seed, $header->seats, $header->options, $moves);
            }
        }

        throw ConcurrentMove::unsettled($gameId, self::READS);
    }

    #[Override]
    public function header(string $gameId): ?LogHeader
    {
        $table = $this->connection->fetchAssociative(\sprintf('SELECT game, version, seed, seats, options, moves, revision FROM %s WHERE game_id = ?', GameEngineTables::TABLES), [$gameId]);

        if (false === $table) {
            return null;
        }

        $log = GameLog::fromArray([
            'game_id' => $gameId,
            'game' => $table['game'],
            'version' => $table['version'],
            'seed' => $table['seed'],
            'seats' => $this->decode($table['seats'], $gameId),
            'options' => $this->decode($table['options'], $gameId),
            'moves' => [],
        ]);

        return new LogHeader($gameId, $log->game, $log->version, $log->seed, $log->seats, $log->options, $this->integer($table['moves'], $gameId), $this->integer($table['revision'], $gameId));
    }

    /**
     * @return Generator<int, LoggedMove>
     */
    #[Override]
    public function moves(string $gameId, int $from, int $to): Generator
    {
        $drew = Type::getType(Types::BOOLEAN);
        $platform = $this->connection->getDatabasePlatform();
        $next = max(1, $from);

        while ($next <= $to) {
            $rows = $this->connection->fetchAllAssociative(
                \sprintf('SELECT number, type, seat, payload, drew FROM %s WHERE game_id = ? AND number >= ? AND number <= ? ORDER BY number', GameEngineTables::MOVES),
                [$gameId, $next, min($to, $next + self::WINDOW - 1)],
            );

            foreach ($rows as $row) {
                if ($this->integer($row['number'], $gameId) !== $next) {
                    return;
                }

                $seat = $row['seat'];
                $type = $row['type'];

                if (!\is_string($type) || (null !== $seat && !\is_string($seat))) {
                    throw InvalidGame::because(\sprintf('The move %d of the log of the table "%s" has a type or a seat that is not a text.', $next, $gameId));
                }

                $payload = [];

                foreach ($this->decode($row['payload'], $gameId) as $name => $value) {
                    $payload[(string) $name] = $value;
                }

                yield new LoggedMove($next, new Move($type, $seat, $payload), true === $drew->convertToPHPValue($row['drew'], $platform));
                ++$next;
            }

            if ([] === $rows) {
                return;
            }
        }
    }

    #[Override]
    public function append(LogHeader $read, LoggedMove $move): void
    {
        if ($move->sequence !== $read->sequence + 1) {
            throw InvalidGame::because(\sprintf('The move %d cannot follow the %d moves of the table "%s".', $move->sequence, $read->sequence, $read->gameId));
        }

        if ($read->sequence >= GameLog::MAX_MOVES) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" holds more than %d moves.', $read->gameId, GameLog::MAX_MOVES));
        }

        $this->connection->transactional(function (Connection $connection) use ($read, $move): void {
            $this->count($connection, $read, 1);
            self::insert($connection, $read->gameId, $move);
        });
    }

    #[Override]
    public function removeLast(LogHeader $read): void
    {
        if ($read->sequence < 1) {
            throw InvalidGame::because(\sprintf('The table "%s" has no move to take back.', $read->gameId));
        }

        $this->connection->transactional(function (Connection $connection) use ($read): void {
            $this->count($connection, $read, -1);
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE game_id = ? AND number >= ?', GameEngineTables::MOVES), [$read->gameId, $read->sequence]);
        });
    }

    /**
     * Forgets a table and its moves, when the application purges the tables it no longer keeps; false when there was no
     * such table.
     */
    public function delete(string $gameId): bool
    {
        return $this->connection->transactional(static function (Connection $connection) use ($gameId): bool {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE game_id = ?', GameEngineTables::MOVES), [$gameId]);

            return 0 < $connection->executeStatement(\sprintf('DELETE FROM %s WHERE game_id = ?', GameEngineTables::TABLES), [$gameId]);
        });
    }

    /**
     * Changes the number of moves of a table that holds those the caller read, at the revision it read, and moves its
     * revision on; or refuses.
     *
     * @throws ConcurrentMove when it holds another number of moves or is at another revision
     * @throws InvalidGame    when there is no such table
     */
    private function count(Connection $connection, LogHeader $read, int $change): void
    {
        $changed = $connection->executeStatement(
            \sprintf('UPDATE %s SET moves = moves + ?, revision = revision + 1 WHERE game_id = ? AND moves = ? AND revision = ?', GameEngineTables::TABLES),
            [$change, $read->gameId, $read->sequence, $read->revision],
        );

        if (1 === $changed) {
            return;
        }

        $header = $this->header($read->gameId) ?? throw InvalidGame::because(\sprintf('No table has the identifier "%s".', $read->gameId));

        throw $header->sequence === $read->sequence ? ConcurrentMove::revised($read->gameId, $read->revision, $header->revision) : ConcurrentMove::at($read->gameId, $read->sequence, $header->sequence);
    }

    private static function insert(Connection $connection, string $gameId, LoggedMove $move): void
    {
        $connection->insert(GameEngineTables::MOVES, [
            'game_id' => $gameId,
            'number' => $move->sequence,
            'type' => $move->move->type,
            'seat' => $move->move->seat,
            'payload' => self::encode($move->move->payload),
            'drew' => $move->drew ? 1 : 0,
        ]);
    }

    /**
     * @param array<mixed> $value
     */
    private static function encode(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @throws InvalidGame when the column is not a whole number
     */
    private function integer(mixed $value, string $gameId): int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && 1 === preg_match('/^-?\d{1,18}$/D', $value) => (int) $value,
            default => throw InvalidGame::because(\sprintf('The log of the table "%s" holds a count that is not a whole number.', $gameId)),
        };
    }

    /**
     * @return array<mixed>
     *
     * @throws InvalidGame when the column is not a JSON array or object
     */
    private function decode(mixed $json, string $gameId): array
    {
        try {
            $value = \is_string($json) ? json_decode($json, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR) : null;
        } catch (JsonException $jsonException) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" holds a column that is not JSON.', $gameId), $jsonException);
        }

        return \is_array($value) ? $value : throw InvalidGame::because(\sprintf('The log of the table "%s" holds a column that is not a JSON array or object.', $gameId));
    }
}
