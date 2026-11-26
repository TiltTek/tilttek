<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Contract;

use Tilttek\Kudos\KudosCancelled;
use Tilttek\Kudos\KudosGiven;

/**
 * Told once of thanks given or cancelled, after the journal wrote them: the application relays the event — a
 * notification, a message on its bus — if it wants to. A request repeated under the same key tells nothing again.
 */
interface KudosObserver
{
    public function given(KudosGiven $event): void;

    public function cancelled(KudosCancelled $event): void;
}
