<?php

declare(strict_types=1);

/*
 * The fuzz target of a published journal, run by `phil-dev fuzz`: any JSON text either is refused with the library's
 * own `InvalidSecretRoll`, or gives a journal whose check lists its faults, never another exception.
 */

use Tilttek\SecretRolls\Exception\InvalidSecretRoll;
use Tilttek\SecretRolls\Journal\SceneJournal;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 32, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $journal = SceneJournal::fromArray($data);
    } catch (InvalidSecretRoll) {
        // The refusal the reader promises for a journal it cannot read.
        return;
    }

    $journal->problems();
});
$config->setMaxLen(4096);
