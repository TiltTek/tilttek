<?php

declare(strict_types=1);

/*
 * The front controller `phil-dev bench:worker` serves under FrankenPHP in worker mode: the roller and the
 * probabilities are built once, then each request asks for what a page of statistics shows — a distribution with its
 * mean and standard deviation, the chance of rolling at least a value, and the chance of an event on four dice.
 */

use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Probabilities;

require dirname(__DIR__, 4).'/vendor/autoload.php';

$dice = new DiceRoller();
$probabilities = new Probabilities();

$page = static function () use ($dice, $probabilities): void {
    $distribution = $probabilities->distribution($dice->parse('4d6kh3'));
    $chance = $probabilities->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), $dice->parse('4d6'));

    echo $distribution->mean(), ' ', round($distribution->standardDeviation(), 3), ' ', $distribution->atLeast(15), ' ', $chance->probability, "\n";
};

if (!function_exists('frankenphp_handle_request')) {
    $page();

    return;
}

while (frankenphp_handle_request($page)) {
    gc_collect_cycles();
}
