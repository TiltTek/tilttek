<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\MemberRating;

/**
 * The ratings a system is given before a result, built from plain ratings.
 */
final class Current
{
    /**
     * @param array<string, Rating> $ratings
     * @param array<string, int>    $games
     *
     * @return array<string, MemberRating>
     */
    public static function of(RatingSystem $system, array $ratings, array $games = []): array
    {
        $current = [];

        foreach ($ratings as $member => $rating) {
            $current[$member] = new MemberRating('chess', $member, $system->id(), $rating, $system->score($rating), $games[$member] ?? 0);
        }

        return $current;
    }
}
