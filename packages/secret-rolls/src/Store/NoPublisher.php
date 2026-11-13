<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Store;

use Override;
use Tilttek\SecretRolls\Contract\JournalPublisher;
use Tilttek\SecretRolls\Journal\JournalEntry;

/**
 * The publisher of an application that pushes nothing: the table reads the journal when it asks for it.
 */
final readonly class NoPublisher implements JournalPublisher
{
    #[Override]
    public function publish(JournalEntry $entry): void
    {
    }
}
