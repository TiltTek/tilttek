<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Application;

use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Signal\Audience;

/**
 * The two private topics of each table: the signals the whole table sees, and those of the game master alone. A
 * prefix, then the table's reference, encoded so that it stays one segment.
 */
final readonly class SignalTopics
{
    public const string PREFIX = 'urn:tilttek:table-safety:';

    public function __construct(
        private string $prefix = self::PREFIX,
    ) {
    }

    public function topic(ResourceReference $table, Audience $audience): string
    {
        return $this->prefix.rawurlencode($table->toString()).(Audience::GameMaster === $audience ? '/game-master' : '');
    }

    /**
     * The topics a viewer reads: the table's, and the game master's too for the game master.
     *
     * @return list<string>
     */
    public function readBy(ResourceReference $table, bool $gameMaster): array
    {
        return $gameMaster ? [$this->topic($table, Audience::Table), $this->topic($table, Audience::GameMaster)] : [$this->topic($table, Audience::Table)];
    }
}
