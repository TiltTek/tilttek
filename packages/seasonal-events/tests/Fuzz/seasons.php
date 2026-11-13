<?php

declare(strict_types=1);

/*
 * The fuzz target of the season definitions, run by `phil-dev fuzz`: any JSON text is either refused with the
 * library's own exception or gives seasons that, written and read back, are the same seasons, and whose moment
 * questions answer without error.
 */
use Tilttek\SeasonalEvents\Exception\SeasonalEventsException;
use Tilttek\SeasonalEvents\Period;
use Tilttek\SeasonalEvents\SeasonReader;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $data = json_decode($input, true, 16);

    if (!is_array($data)) {
        return;
    }

    try {
        $seasons = SeasonReader::all($data);
    } catch (SeasonalEventsException) {
        // The refusals the reader promises.
        return;
    }

    $moment = new DateTimeImmutable('2026-12-20 12:00', new DateTimeZone('UTC'));

    foreach ($seasons as $season) {
        if (SeasonReader::write(SeasonReader::season(SeasonReader::write($season))) !== SeasonReader::write($season)) {
            throw new LogicException('A season written and read back is another season.');
        }

        $period = $season->periodAt($moment);
        $season->schedule->nextPeriod($moment);

        foreach ($period instanceof Period ? $season->events : [] as $event) {
            $season->eventIn($event, $period)->isAnnouncedAt($moment);
        }
    }
});
$config->setMaxLen(2048);
