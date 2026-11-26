<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Kudos\Contract\KudosPreferences;

/**
 * Preferences where one receiver declines thanks from one giver.
 */
final readonly class Declines implements KudosPreferences
{
    public function __construct(
        private string $receiver,
        private string $giver,
    ) {
    }

    #[Override]
    public function accepts(ResourceReference $receiver, ResourceReference $giver): bool
    {
        return $receiver->toString() !== $this->receiver || $giver->toString() !== $this->giver;
    }
}
