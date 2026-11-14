<?php

declare(strict_types=1);

namespace Tilttek\Rating\Contract;

use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;

/**
 * A way of rating players from the results of their games: `Elo\EloSystem` and `OpenSkill\OpenSkillSystem`. A system
 * keeps nothing between calls; the same ratings and the same result always give the same new ratings.
 */
interface RatingSystem
{
    /**
     * The identifier of the system and of its model, kept with each rating: ratings of one system are never read by
     * another.
     */
    public function id(): string;

    /**
     * The rating of a member before any rated game.
     */
    public function initial(): Rating;

    /**
     * The new rating of each member of the result.
     *
     * @param array<string, MemberRating> $current the rating of every member of the result, by member
     *
     * @return array<string, Rating> by member, for every member of the result
     */
    public function rate(GameResult $result, array $current): array;

    /**
     * The value the standings are ordered by, in hundredths: the Elo rating, or the conservative estimate of a skill.
     */
    public function score(Rating $rating): int;

    /**
     * Whether the rating is still uncertain by the system's own measure, whatever the games played: a skill whose
     * deviation is above the threshold of an OpenSkill system; never for Elo, which has no such measure.
     *
     * @throws InvalidRating when the rating is not one of this system
     */
    public function isProvisional(Rating $rating): bool;

    /**
     * A rating as `Rating::toArray()` wrote it.
     *
     * @param array<mixed> $values
     *
     * @throws InvalidRating when the values are not a rating of this system
     */
    public function restore(array $values): Rating;
}
