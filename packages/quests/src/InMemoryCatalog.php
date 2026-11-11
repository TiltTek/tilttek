<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Override;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Exception\UnknownQuest;

/**
 * The quests given when it is built, their chains checked: from code, or from a configuration by `fromArray()`.
 */
final readonly class InMemoryCatalog implements Catalog
{
    /**
     * @var array<string, Quest>
     */
    private array $quests;

    /**
     * @param list<Quest> $quests
     *
     * @throws InvalidQuest
     */
    public function __construct(array $quests = [])
    {
        $byCode = [];

        foreach ($quests as $quest) {
            if (isset($byCode[$quest->code])) {
                throw InvalidQuest::because(\sprintf('Two quests are named "%s".', $quest->code));
            }

            $byCode[$quest->code] = $quest;
        }

        Chains::check($byCode);
        ksort($byCode, \SORT_STRING);
        $this->quests = $byCode;
    }

    /**
     * The quests of a configuration: definitions by code, read in a timezone and by the reader's schedule readers.
     *
     * @param array<array-key, mixed> $definitions
     *
     * @throws InvalidQuest
     */
    public static function fromArray(array $definitions, QuestReader $reader = new QuestReader()): self
    {
        $quests = [];

        foreach ($definitions as $code => $definition) {
            if (!\is_array($definition)) {
                throw InvalidQuest::because(\sprintf('The quest "%s" is a list of values.', $code));
            }

            $quests[] = $reader->read((string) $code, $definition);
        }

        return new self($quests);
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->quests);
    }

    #[Override]
    public function has(string $code): bool
    {
        return isset($this->quests[$code]);
    }

    #[Override]
    public function get(string $code): Quest
    {
        return $this->quests[$code] ?? throw UnknownQuest::named($code);
    }
}
