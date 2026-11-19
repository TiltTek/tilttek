<?php

declare(strict_types=1);

/*
 * The fuzz target of a published proof, run by `phil-dev fuzz`: any text gives the faults of the proof, a proof that
 * does not read being one fault, never an exception.
 */

use Tilttek\Raffle\RaffleVerifier;

require getenv('PHIL_DEV_AUTOLOAD');

$verifier = new RaffleVerifier();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($verifier): void {
    $verifier->problemsOfJson($input);
});
$config->setMaxLen(4096);
