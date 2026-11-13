<?php

declare(strict_types=1);

/*
 * The fuzz target of the snapshots a cache gives back, run by `phil-dev fuzz`: any JSON text is either refused with the
 * library's own exceptions or gives a snapshot that, written and read back, is the same snapshot.
 */

use Tilttek\GameEngine\Exception\GameEngineException;
use Tilttek\GameEngine\Snapshot;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $data = json_decode($input, true, 16);

    if (!is_array($data)) {
        return;
    }

    try {
        $snapshot = Snapshot::fromArray($data);
    } catch (GameEngineException) {
        // The refusals the snapshot promises.
        return;
    }

    if (Snapshot::fromArray($snapshot->toArray())->toArray() !== $snapshot->toArray()) {
        throw new LogicException('A snapshot written and read back is another snapshot.');
    }
});
$config->setMaxLen(2048);
