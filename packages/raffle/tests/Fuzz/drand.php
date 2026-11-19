<?php

declare(strict_types=1);

/*
 * The fuzz target of the answers of a drand relay, run by `phil-dev fuzz`: any text, read as the information of a
 * chain and as a round of each scheme, is refused with `BeaconRefused` or gives a chain or a round, never another
 * exception.
 */

use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Drand\DrandResponse;
use Tilttek\Raffle\Drand\DrandScheme;
use Tilttek\Raffle\Exception\BeaconRefused;

require getenv('PHIL_DEV_AUTOLOAD');

$chains = [DrandChains::quicknet(), DrandChains::mainnet()];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($chains): void {
    foreach ($chains as $chain) {
        try {
            DrandResponse::info($input, $chain);
        } catch (BeaconRefused) {
            // The refusal the reader promises for an answer that does not describe the chain.
        }
    }

    foreach (DrandScheme::cases() as $scheme) {
        try {
            DrandResponse::beacon($input, $scheme, 7, BeaconVerification::RelayQuorum);
        } catch (BeaconRefused) {
            // The refusal the reader promises for an answer that is not the round.
        }
    }
});
$config->setMaxLen(4096);
