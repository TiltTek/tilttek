<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Preferences;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Kudos\Contract\KudosPreferences;

/**
 * Every member takes thanks from everyone: the preferences of an application that offers no choice yet.
 */
final readonly class AcceptAll implements KudosPreferences
{
    #[Override]
    public function accepts(ResourceReference $receiver, ResourceReference $giver): bool
    {
        return true;
    }
}
