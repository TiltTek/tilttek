<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Fixture;

use LogicException;
use Override;
use Tilttek\SecretRolls\Contract\SecretRollStore;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\JournalHead;

/**
 * A store whose journals cannot be read whole: what a roll needs is their head.
 */
final readonly class HeadOnlyStore implements SecretRollStore
{
    public function __construct(
        private SecretRollStore $inner,
    ) {
    }

    #[Override]
    public function entries(string $scene): array
    {
        throw new LogicException(\sprintf('The journal of %s was read whole.', $scene));
    }

    #[Override]
    public function head(string $scene): JournalHead
    {
        return $this->inner->head($scene);
    }

    #[Override]
    public function commit(JournalEntry $entry, HiddenRoll $roll): void
    {
        $this->inner->commit($entry, $roll);
    }

    #[Override]
    public function append(JournalEntry $entry): void
    {
        $this->inner->append($entry);
    }

    #[Override]
    public function hidden(string $roll): ?HiddenRoll
    {
        return $this->inner->hidden($roll);
    }
}
