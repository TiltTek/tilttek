<?php

declare(strict_types=1);

/*
 * The fuzz target of the token rules of a table, run by `phil-dev fuzz`: any text gives rules that write back as they
 * were read, or is refused with `InvalidTokenRule`, never another exception.
 */

use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\TableRules;

require getenv('PHIL_DEV_AUTOLOAD');

$table = ResourceReference::of('table', '1');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($table): void {
    try {
        $rules = TableRules::fromJson($table, $input);
    } catch (InvalidTokenRule) {
        return;
    }

    foreach ($rules->kinds() as $kind) {
        for ($balance = 0; $balance <= $kind->cap; $balance += max(1, intdiv($kind->cap, 7))) {
            $refreshed = $kind->atSessionStart($balance);

            if ($refreshed < 0 || $refreshed > $kind->cap) {
                throw new LogicException(sprintf('The token "%s" refreshes %d to %d, outside 0 to its cap.', $kind->key, $balance, $refreshed));
            }
        }
    }

    if (TableRules::fromArray($table, $rules->toArray())->toArray() !== $rules->toArray()) {
        throw new LogicException('The rules written do not read back the same.');
    }
});
$config->setMaxLen(4096);
