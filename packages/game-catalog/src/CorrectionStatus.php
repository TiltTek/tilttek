<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * Where a correction stands: waiting for the administration, accepted, or refused.
 */
enum CorrectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
}
