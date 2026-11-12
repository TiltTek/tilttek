<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use SensitiveParameter;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * The share of luck in the score of a team, when the application enables it on a board: dice rolled once for each team
 * and period — 1 to 10 dice of 2 to 100 faces —, whose sum is added to the scores of its members.
 *
 * The roll is drawn from the application's secret, the board, the period and the team: the same team always gets the
 * same roll for a period, whoever looks and however often, and nobody without the secret can foresee it.
 *
 * *Two six-sided dice*: `new Chance(2, 6)`.
 */
final readonly class Chance
{
    public const int MAX_DICE = 10;

    public const int MAX_FACES = 100;

    public function __construct(
        public int $dice = 1,
        public int $faces = 6,
    ) {
        if ($dice < 1 || $dice > self::MAX_DICE || $faces < 2 || $faces > self::MAX_FACES) {
            throw InvalidLeaderboard::because(\sprintf('A team rolls 1 to %d dice of 2 to %d faces; %dd%d is not one of them.', self::MAX_DICE, self::MAX_FACES, $dice, $faces));
        }
    }

    /**
     * The roll of the team on the board key in the period.
     *
     * @throws InvalidLeaderboard when the secret is empty
     */
    public function roll(#[SensitiveParameter] string $secret, string $board, string $period, string $team): int
    {
        if ('' === $secret) {
            throw InvalidLeaderboard::because('The luck in a team score is drawn from a secret of the application; none was given.');
        }

        $randomizer = new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', json_encode([$board, $period, $team], \JSON_THROW_ON_ERROR), $secret, true)));
        $sum = 0;

        for ($die = 0; $die < $this->dice; ++$die) {
            $sum += $randomizer->getInt(1, $this->faces);
        }

        return $sum;
    }
}
