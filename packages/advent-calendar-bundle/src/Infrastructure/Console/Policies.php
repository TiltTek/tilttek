<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Console;

use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\PolicyKind;

/**
 * An opening policy in the words of the commands, which are in English like the rest of the console.
 */
final class Policies
{
    private function __construct()
    {
    }

    public static function describe(OpeningPolicy $policy): string
    {
        return match ($policy->kind) {
            PolicyKind::UntilClosing => 'openable until closing',
            PolicyKind::SameDay => 'openable on its day only',
            PolicyKind::CatchUp => \sprintf('openable on its day and %d more', $policy->catchUpDays),
        };
    }
}
