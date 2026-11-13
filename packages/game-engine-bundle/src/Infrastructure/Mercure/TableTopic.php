<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Mercure;

use InvalidArgumentException;

/**
 * The Mercure topic of each table, from a template where `{table}` stands for the table's identifier:
 * `urn:tilttek-game-engine:table:{table}`. An identifier holds only ASCII letters, digits, `:`, `.`, `_` and `-`,
 * which a topic takes as they are.
 */
final readonly class TableTopic
{
    public const string PLACEHOLDER = '{table}';

    public const string DEFAULT = 'urn:tilttek-game-engine:table:{table}';

    public function __construct(
        private string $template = self::DEFAULT,
    ) {
        if (1 !== substr_count($template, self::PLACEHOLDER)) {
            throw new InvalidArgumentException(\sprintf('The topic of the tables "%s" holds "%s" once.', $template, self::PLACEHOLDER));
        }
    }

    public function of(string $gameId): string
    {
        return str_replace(self::PLACEHOLDER, $gameId, $this->template);
    }
}
