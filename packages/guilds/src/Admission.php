<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

/**
 * How a member enters a guild: at once when it is open, or by a request its founder or an administrator approves.
 */
enum Admission: string
{
    case Open = 'open';

    case OnRequest = 'on_request';
}
