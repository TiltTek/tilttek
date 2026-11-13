<?php

declare(strict_types=1);

/*
 * The fuzz target of the verification of a revealed roll, run by `phil-dev fuzz`: any JSON text, read as an opening and
 * checked against a commitment, gives true or false, never another exception.
 */

use Tilttek\SecretRolls\RollVerifier;

require getenv('PHIL_DEV_AUTOLOAD');

$verifier = new RollVerifier();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($verifier): void {
    try {
        $data = json_decode($input, true, 32, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    $commitment = $data['commitment'] ?? '';
    unset($data['commitment']);
    $verifier->verifyPublished($data, is_string($commitment) ? $commitment : '');
});
$config->setMaxLen(2048);
