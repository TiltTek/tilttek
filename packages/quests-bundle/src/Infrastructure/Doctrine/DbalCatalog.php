<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use JsonException;
use Override;
use Tilttek\Quests\Chains;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\WritableCatalog;

/**
 * The quests in `tilttek_quests_definition`, through a DBAL connection, for an application whose quests are edited
 * rather than configured: each definition is kept as JSON, as `QuestReader` reads it, and read again with every
 * question — a quest saved is seen at once.
 */
final readonly class DbalCatalog implements WritableCatalog
{
    public function __construct(
        private Connection $connection,
        private QuestReader $reader,
    ) {
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->quests());
    }

    #[Override]
    public function has(string $code): bool
    {
        return isset($this->quests()[$code]);
    }

    #[Override]
    public function get(string $code): Quest
    {
        return $this->quests()[$code] ?? throw UnknownQuest::named($code);
    }

    #[Override]
    public function save(string $code, array $definition): Quest
    {
        $quest = $this->reader->read($code, $definition);
        $quests = $this->quests();
        $quests[$code] = $quest;
        Chains::check($quests);

        $this->connection->transactional(static function (Connection $connection) use ($code, $definition): void {
            $connection->delete(QuestsTables::DEFINITIONS, ['code' => $code]);
            $connection->insert(QuestsTables::DEFINITIONS, ['code' => $code, 'definition' => json_encode($definition, \JSON_THROW_ON_ERROR)]);
        });

        return $quest;
    }

    #[Override]
    public function remove(string $code): void
    {
        $quests = $this->quests();
        unset($quests[$code]);
        Chains::check($quests);
        $this->connection->delete(QuestsTables::DEFINITIONS, ['code' => $code]);
    }

    /**
     * @return array<string, Quest> by code
     */
    private function quests(): array
    {
        $quests = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT code, definition FROM %s ORDER BY code', QuestsTables::DEFINITIONS)) as $row) {
            $code = Rows::text($row['code']);

            try {
                $definition = json_decode(Rows::text($row['definition']), true, 64, \JSON_THROW_ON_ERROR);
            } catch (JsonException $jsonException) {
                throw InvalidQuest::because(\sprintf('The definition of "%s" is not JSON: %s.', $code, $jsonException->getMessage()));
            }

            if (!\is_array($definition)) {
                throw InvalidQuest::because(\sprintf('The definition of "%s" is a list of values.', $code));
            }

            $quests[$code] = $this->reader->read($code, $definition);
        }

        return $quests;
    }
}
