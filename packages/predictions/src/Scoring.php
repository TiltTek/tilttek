<?php

declare(strict_types=1);

namespace Tilttek\Predictions;

/**
 * How a question scores its predictions. `Choice`: the member picks one outcome, and a right pick earns the question's
 * points. `Probability`: the member gives each outcome a probability in percent, and earns a share of the points that
 * grows as the Brier score of the forecast falls, which rewards calibrated confidence rather than a bold guess.
 */
enum Scoring: string
{
    case Choice = 'choice';

    case Probability = 'probability';
}
