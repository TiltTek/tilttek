<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Fixture;

use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\OpeningPolicy;
use Tilttek\AdventCalendar\Surprise;

/**
 * The calendars of the tests: four boxes from the 1st of December 2026 in Toronto, the fourth a badge.
 */
final class Calendars
{
    public const string ZONE = 'America/Toronto';

    private function __construct()
    {
    }

    public static function advent(?OpeningPolicy $policy = null, string $id = 'advent-2026'): Calendar
    {
        return Calendar::of($id, self::ZONE, '2026-12-01', [Surprise::points(10), Surprise::points(20), Surprise::code('gifts'), Surprise::badge('advent-week')], $policy);
    }

    /**
     * The same calendar as the configuration writes it.
     *
     * @return array{title: string, timezone: string, first_date: string, closing_date: ?string, policy: string, catch_up_days: int, published: bool, surprises: list<array{kind: string, reference: string, quantity: int}>}
     */
    public static function config(string $policy = 'until_closing', int $catchUpDays = 0, bool $published = true): array
    {
        return [
            'title' => 'Advent 2026',
            'timezone' => self::ZONE,
            'first_date' => '2026-12-01',
            'closing_date' => null,
            'policy' => $policy,
            'catch_up_days' => $catchUpDays,
            'published' => $published,
            'surprises' => [
                ['kind' => 'points', 'reference' => 'points', 'quantity' => 10],
                ['kind' => 'points', 'reference' => 'points', 'quantity' => 20],
                ['kind' => 'code', 'reference' => 'gifts', 'quantity' => 1],
                ['kind' => 'badge', 'reference' => 'advent-week', 'quantity' => 1],
            ],
        ];
    }
}
