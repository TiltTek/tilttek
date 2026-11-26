<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

/**
 * Where a question stands: open until its deadline, then waiting for its outcome; resolved with the outcome the
 * application declared; or cancelled, without any points.
 */
enum QuestionStatus: string
{
    case Open = 'open';

    case Resolved = 'resolved';

    case Cancelled = 'cancelled';
}
