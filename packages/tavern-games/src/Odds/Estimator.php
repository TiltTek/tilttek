<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Odds;

use Random\Randomizer;
use Tilttek\TavernGames\Contract\Simulation;
use Tilttek\TavernGames\Exception\InvalidTavernGame;

/**
 * Estimates the odds of a position by playing it to its end many times at random.
 */
final class Estimator
{
    /** The most simulated endings of one estimate: its cost stays bounded. */
    public const int MAX_SAMPLES = 100_000;

    /**
     * @throws InvalidTavernGame when the samples are not 1 to `MAX_SAMPLES`
     */
    public static function estimate(Simulation $simulation, int $samples, Randomizer $random): Estimate
    {
        if ($samples < 1 || $samples > self::MAX_SAMPLES) {
            throw InvalidTavernGame::because(\sprintf('An estimate plays 1 to %d endings.', self::MAX_SAMPLES));
        }

        $counts = ['win' => 0, 'tie' => 0, 'loss' => 0];

        for ($sample = 0; $sample < $samples; ++$sample) {
            ++$counts[$simulation->trial($random)->value];
        }

        return new Estimate($counts['win'], $counts['tie'], $counts['loss']);
    }
}
