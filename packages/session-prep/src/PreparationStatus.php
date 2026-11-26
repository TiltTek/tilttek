<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep;

/**
 * Where a preparation stands: being written, ready for the table, or closed once the session is over.
 */
enum PreparationStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Closed = 'closed';
}
