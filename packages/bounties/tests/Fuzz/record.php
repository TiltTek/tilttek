<?php

declare(strict_types=1);

/*
 * The fuzz target of a bounty read back from storage, run by `phil-dev fuzz`: any JSON text gives a bounty that
 * writes back as it was read, or is refused with `InvalidBounty`, never another exception.
 */

use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\Exception\InvalidBounty;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $record = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($record)) {
        return;
    }

    try {
        $bounty = Bounty::fromArray($record);
    } catch (InvalidBounty) {
        return;
    }

    if (Bounty::fromArray($bounty->toArray())->toArray() !== $bounty->toArray()) {
        throw new LogicException('The bounty written does not read back the same.');
    }
});
$config->setMaxLen(8192);
