<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Contract;

use Tilttek\SecretRolls\Journal\JournalEntry;

/**
 * Told of each entry right after the store appended it: a dice tower, an overlay or a chat bridge pushes it to the
 * table. An entry is public: a committed roll shows its commitment, never its result.
 */
interface JournalPublisher
{
    public function publish(JournalEntry $entry): void;
}
