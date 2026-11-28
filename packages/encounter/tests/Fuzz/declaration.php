<?php

declare(strict_types=1);

/*
 * The fuzz target of the rules of difficulty a game system declares as data, run by `phil-dev fuzz`: any JSON is either
 * refused with the library's own exceptions or gives rules that assess an encounter within their bounds, or refuse it
 * with the library's own exceptions.
 */

use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\Exception\EncounterException;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $rules = Declaration::read($data);
        $assessment = $rules->assess([
            new Participant('hero', Kind::Character, '1', 'player'),
            new Participant('foes', Kind::Adversary, '1', 'gm', 3),
        ]);
    } catch (EncounterException) {
        // The refusals the library promises for rules it does not keep, or an encounter they do not rate.
        return;
    }

    if ($assessment->cost < 0 || [] === $assessment->bands || 3 !== $assessment->adversaries || 1 !== $assessment->characters) {
        throw new LogicException('An assessment is out of its shape.');
    }
});
$config->setMaxLen(2048);
