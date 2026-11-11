<?php

declare(strict_types=1);

namespace Tilttek\Quests;

/**
 * What a step counts: the activities it accepts — *play three games* — or their quantities, summed — *read 300
 * pages*.
 */
enum StepMeasure: string
{
    case Count = 'count';
    case Total = 'total';
}
