<?php

declare(strict_types=1);

/*
 * The fuzz target of a scenario, run by `phil-dev fuzz`: any text gives a scenario whose order holds every puzzle once,
 * each after the puzzles it requires, or is refused with `InvalidScenario`, never another exception.
 */

use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Scenario;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $scenario = Scenario::fromJson($input);
    } catch (InvalidScenario) {
        return;
    }

    $order = $scenario->order();

    if (count($order) !== count($scenario->puzzles()) || count(array_unique($order)) !== count($order)) {
        throw new LogicException('The order of a scenario holds every puzzle once.');
    }

    $place = array_flip($order);

    foreach ($scenario->puzzles() as $puzzle) {
        foreach ($puzzle->unlock->requires as $required) {
            if ($puzzle->unlock->minimum === count($puzzle->unlock->requires) && $place[$required] > $place[$puzzle->id]) {
                throw new LogicException(sprintf('The puzzle "%s" comes before "%s", which it requires.', $puzzle->id, $required));
            }
        }
    }
});
$config->setMaxLen(4096);
