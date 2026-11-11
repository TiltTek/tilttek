<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Exception\UnknownQuest;

/**
 * The quests of an application, from its configuration or its database.
 */
interface Catalog
{
    /**
     * Every quest, by code.
     *
     * @return list<Quest>
     */
    public function all(): array;

    public function has(string $code): bool;

    /**
     * @throws UnknownQuest
     */
    public function get(string $code): Quest;
}
